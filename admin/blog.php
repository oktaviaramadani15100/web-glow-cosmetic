<?php
session_start();
require_once "../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../customer/login.php");
    exit;
}

$adminId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? 0;

if (!function_exists("e")) {
    function e($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            "UTF-8"
        );
    }
}

$stmt = $conn->prepare("
    SELECT id, name, email, role
    FROM users
    WHERE id = :id AND role = 'admin'
    LIMIT 1
");
$stmt->execute(["id" => $adminId]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("Data admin tidak ditemukan. Silakan login kembali.");
}

$adminName = $admin["name"] ?: "Admin";
$adminRole = $admin["role"] ?: "Administrator";

$hari = [
    "Sunday" => "Minggu",
    "Monday" => "Senin",
    "Tuesday" => "Selasa",
    "Wednesday" => "Rabu",
    "Thursday" => "Kamis",
    "Friday" => "Jumat",
    "Saturday" => "Sabtu"
];

$bulan = [
    1 => "Januari",
    2 => "Februari",
    3 => "Maret",
    4 => "April",
    5 => "Mei",
    6 => "Juni",
    7 => "Juli",
    8 => "Agustus",
    9 => "September",
    10 => "Oktober",
    11 => "November",
    12 => "Desember"
];

date_default_timezone_set("Asia/Jakarta");

$namaHari = $hari[date("l")];
$tanggalHariIni = $namaHari . ", " . date("d") . " " .
    $bulan[(int) date("n")] . " " . date("Y");


$alert = "";
$alertType = "";
$uploadDir = __DIR__ . "/../uploads/blog/";
$uploadUrl = "../uploads/blog/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function buatSlug($judul)
{
    $slug = strtolower(trim($judul));
    $slug = preg_replace("/[^a-z0-9]+/i", "-", $slug);
    return trim($slug, "-");
}

function slugUnik($conn, $judul, $id = 0)
{
    $dasar = buatSlug($judul);

    if ($dasar === "") {
        $dasar = "artikel";
    }

    $slug = $dasar;
    $nomor = 1;

    while (true) {
        $sql = "SELECT id FROM blog_posts WHERE slug = :slug";
        $params = [":slug" => $slug];

        if ($id > 0) {
            $sql .= " AND id != :id";
            $params[":id"] = $id;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $dasar . "-" . $nomor;
        $nomor++;
    }
}

function hapusGambarBlog($path)
{
    if (!$path) {
        return;
    }

    $namaFile = basename($path);
    $lokasi = __DIR__ . "/../uploads/blog/" . $namaFile;

    if (is_file($lokasi)) {
        unlink($lokasi);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $aksi = $_POST["aksi"] ?? "";

        if ($aksi === "simpan" || $aksi === "hapus") {
            $token = $_POST["csrf_token"] ?? "";

            if (
                empty($_SESSION["csrf_blog"]) ||
                !hash_equals($_SESSION["csrf_blog"], $token)
            ) {
                throw new Exception(
                    "Permintaan tidak valid. Silakan muat ulang halaman."
                );
            }
        }

        if ($aksi === "simpan") {
            $id = (int) ($_POST["id"] ?? 0);
            $title = trim($_POST["title"] ?? "");
            $content = trim($_POST["content"] ?? "");
            $status = $_POST["status"] ?? "draft";

            if ($title === "" || $content === "") {
                throw new Exception(
                    "Judul dan isi blog wajib diisi."
                );
            }

            if (!in_array($status, ["published", "draft"], true)) {
                throw new Exception("Status blog tidak valid.");
            }

            $blogLama = null;

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "SELECT * FROM blog_posts WHERE id = :id"
                );
                $stmt->execute([":id" => $id]);
                $blogLama = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$blogLama) {
                    throw new Exception("Artikel blog tidak ditemukan.");
                }
            }

            $image = $blogLama["image"] ?? null;
            $gambarBaru = null;

            if (
                isset($_FILES["image"]) &&
                $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {
                if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
                    throw new Exception("Gagal mengunggah gambar.");
                }

                if ($_FILES["image"]["size"] > 3 * 1024 * 1024) {
                    throw new Exception(
                        "Ukuran gambar maksimal 3 MB."
                    );
                }

                $tmp = $_FILES["image"]["tmp_name"];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmp);

                $tipeGambar = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($tipeGambar[$mime])) {
                    throw new Exception(
                        "Format gambar harus JPG, PNG, atau WEBP."
                    );
                }

                $namaFile = bin2hex(random_bytes(16))
                    . "."
                    . $tipeGambar[$mime];

                if (!move_uploaded_file(
                    $tmp,
                    $uploadDir . $namaFile
                )) {
                    throw new Exception(
                        "Gambar gagal disimpan ke folder upload."
                    );
                }

                $gambarBaru = $namaFile;
                $image = $namaFile;
            }

            $slug = slugUnik($conn, $title, $id);

            $authorId = (int) ($_SESSION["user_id"] ?? 0);

            if ($authorId <= 0) {
                throw new Exception(
                    "ID admin tidak ditemukan di session login."
                );
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE blog_posts
                     SET title = :title,
                         slug = :slug,
                         content = :content,
                         image = :image,
                         author_id = :author_id,
                         status = :status,
                         published_at = CASE
                             WHEN :status_date = 'published'
                                 THEN COALESCE(published_at, NOW())
                             ELSE NULL
                         END,
                         updated_at = NOW()
                     WHERE id = :id"
                );

                $stmt->execute([
                    ":title" => $title,
                    ":slug" => $slug,
                    ":content" => $content,
                    ":image" => $image,
                    ":author_id" => $authorId,
                    ":status" => $status,
                    ":status_date" => $status,
                    ":id" => $id
                ]);

                if ($gambarBaru && !empty($blogLama["image"])) {
                    hapusGambarBlog($blogLama["image"]);
                }

                $alert = "Artikel blog berhasil diperbarui.";
            } else {
                $publishedAt = $status === "published"
                    ? date("Y-m-d H:i:s")
                    : null;

                $stmt = $conn->prepare(
                    "INSERT INTO blog_posts
                        (title, slug, content, image, author_id,
                         status, published_at, created_at, updated_at)
                     VALUES
                        (:title, :slug, :content, :image, :author_id,
                         :status, :published_at, NOW(), NOW())"
                );

                $stmt->execute([
                    ":title" => $title,
                    ":slug" => $slug,
                    ":content" => $content,
                    ":image" => $image,
                    ":author_id" => $authorId,
                    ":status" => $status,
                    ":published_at" => $publishedAt
                ]);

                $alert = "Artikel blog berhasil ditambahkan.";
            }

            $alertType = "success";
        } elseif ($aksi === "hapus") {
            $id = (int) ($_POST["id"] ?? 0);

            if ($id <= 0) {
                throw new Exception("ID artikel tidak valid.");
            }

            $stmt = $conn->prepare(
                "SELECT image FROM blog_posts WHERE id = :id"
            );
            $stmt->execute([":id" => $id]);
            $blog = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$blog) {
                throw new Exception("Artikel blog tidak ditemukan.");
            }

            $stmt = $conn->prepare(
                "DELETE FROM blog_posts WHERE id = :id"
            );
            $stmt->execute([":id" => $id]);

            hapusGambarBlog($blog["image"]);

            $alert = "Artikel blog berhasil dihapus.";
            $alertType = "success";
        }
    } catch (Exception $ex) {
        $alert = $ex->getMessage();
        $alertType = "error";

        if (!empty($gambarBaru)) {
            hapusGambarBlog($gambarBaru);
        }
    } catch (PDOException $ex) {
        $alert = "Terjadi kesalahan pada database saat memproses blog.";
        $alertType = "error";

        if (!empty($gambarBaru)) {
            hapusGambarBlog($gambarBaru);
        }
    }

    $_SESSION["blog_flash"] = [
        "message" => $alert,
        "type" => $alertType
    ];

    header("Location: blog.php");
    exit;
}

if (empty($_SESSION["csrf_blog"])) {
    $_SESSION["csrf_blog"] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION["blog_flash"])) {
    $flash = $_SESSION["blog_flash"];
    $alert = $flash["message"];
    $alertType = $flash["type"];
    unset($_SESSION["blog_flash"]);
}

$stmt = $conn->query(
    "SELECT b.id, b.title, b.slug, b.content, b.image,
            b.author_id, b.status, b.published_at,
            b.created_at, b.updated_at,
            u.name AS author_name
     FROM blog_posts b
     LEFT JOIN users u ON u.id = b.author_id
     ORDER BY b.created_at DESC, b.id DESC"
);

$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalBlog = count($blogs);
$totalPublished = 0;
$totalDraft = 0;

foreach ($blogs as $blog) {
    if ($blog["status"] === "published") {
        $totalPublished++;
    } else {
        $totalDraft++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Blog - Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-pages.css">
    <link rel="stylesheet" href="../css/admin-filter-select.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="admin-layout">

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <img src="../img/logo2.png" alt="Glow.Cosmetic">
            </div>

            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
                </a>
                <a href="produk.php" class="menu-item">
                    <i class="fa-solid fa-box"></i><span>Produk</span>
                </a>
                <a href="kategori.php" class="menu-item">
                    <i class="fa-solid fa-layer-group"></i><span>Kategori</span>
                </a>
                <a href="stok.php" class="menu-item">
                    <i class="fa-solid fa-boxes-stacked"></i><span>Stok</span>
                </a>
                <a href="pesanan.php" class="menu-item">
                    <i class="fa-solid fa-cart-shopping"></i><span>Pesanan</span>
                </a>
                <a href="pembayaran.php" class="menu-item">
                    <i class="fa-solid fa-wallet"></i><span>Pembayaran</span>
                </a>
                <a href="pengiriman.php" class="menu-item">
                    <i class="fa-solid fa-truck"></i><span>Pengiriman</span>
                </a>
                <a href="user.php" class="menu-item">
                    <i class="fa-solid fa-users"></i><span>Users</span>
                </a>
                <a href="laporan.php" class="menu-item">
                    <i class="fa-solid fa-chart-column"></i><span>Laporan</span>
                </a>
                <a href="faq.php" class="menu-item">
                    <i class="fa-solid fa-circle-question"></i><span>FAQ</span>
                </a>
                <a href="blog.php" class="menu-item active">
                    <i class="fa-solid fa-book-open"></i><span>Blog</span>
                </a>
            </nav>


            <div class="sidebar-bottom">
                <a href="profile.php" class="admin-profile">
                    <div class="admin-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <div class="admin-profile-info">
                        <strong><?= e($adminName) ?></strong>
                        <span><?= e(ucfirst($adminRole)) ?></span>
                    </div>
                </a>
            </div>

        </aside>

        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="menuButton">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div>
                        <span class="breadcrumb">Manajemen</span>
                        <h1>Blog</h1>
                    </div>
                </div>



                <div class="header-right">

                    <span class="date">
                        <i class="fa-regular fa-calendar"></i>
                        <?= e($tanggalHariIni) ?>
                    </span>

                    <button type="button" class="notification-btn" aria-label="Notifikasi">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notification-dot"></span>
                    </button>

                    <a href="profile.php" class="header-avatar" title="<?= e($adminName) ?>">
                        <?= e(strtoupper(substr(trim($adminName), 0, 1))) ?>
                    </a>

                </div>

            </header>

            <div class="page-content">
                <div class="management-card">

                    <div class="blog-toolbar">
                        <div class="blog-toolbar-left">
                            <div class="search-box blog-search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input
                                    type="text"
                                    id="searchBlog"
                                    placeholder="Cari judul blog..."
                                    autocomplete="off">
                            </div>

                            <select id="statusFilter" data-filter-select
                                aria-label="Filter status blog">
                                <option value="">Semua Status</option>
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>

                        </div>

                        <button
                            class="btn-primary"
                            type="button"
                            onclick="openBlogModal()">
                            <i class="fa-solid fa-plus"></i>
                            Tambah Blog
                        </button>
                    </div>


                    <div class="faq-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-book-open"></i>
                            </div>
                            <div>
                                <p>Total Blog</p>
                                <h3><?= $totalBlog ?></h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div>
                                <p>Published</p>
                                <h3><?= $totalPublished ?></h3>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-file-pen"></i>
                            </div>
                            <div>
                                <p>Draft</p>
                                <h3><?= $totalDraft ?></h3>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>NO</th>
                                    <th>JUDUL</th>
                                    <th>PENULIS</th>
                                    <th>TANGGAL</th>
                                    <th>STATUS</th>
                                    <th>AKSI</th>
                                </tr>
                            </thead>
                            <tbody id="blogTable">
                                <?php if ($blogs): ?>
                                    <?php foreach ($blogs as $index => $blog): ?>
                                        <tr data-status="<?= e($blog["status"]) ?>">
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <div class="blog-entry-cell">
                                                    <?php if (!empty($blog["image"])): ?>
                                                        <img
                                                            class="blog-entry-image"
                                                            src="<?= e($uploadUrl . basename($blog["image"])) ?>"
                                                            alt="Gambar blog"
                                                            loading="lazy">
                                                    <?php endif; ?>
                                                    <strong class="blog-entry-title"><?= e($blog["title"]) ?></strong>
                                                </div>
                                            </td>
                                            <td><?= e($blog["author_name"] ?? "Admin") ?></td>
                                            <td>
                                                <?= e(
                                                    !empty($blog["published_at"])
                                                        ? date("d M Y", strtotime($blog["published_at"]))
                                                        : date("d M Y", strtotime($blog["created_at"]))
                                                ) ?>
                                            </td>
                                            <td>
                                                <?php if ($blog["status"] === "published"): ?>
                                                    <span class="status success">Published</span>
                                                <?php else: ?>
                                                    <span class="status warning">Draft</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="blog-action-buttons">
                                                    <button class="table-action edit"
                                                        type="button"
                                                        title="Edit blog"
                                                        aria-label="Edit blog"
                                                        onclick='editBlog(
                                                        <?= json_encode((int)$blog["id"]) ?>,
                                                        <?= json_encode($blog["title"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                                                        <?= json_encode($blog["content"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                                                        <?= json_encode($blog["status"]) ?>
                                                    )'>
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>

                                                    <form method="POST" class="delete-blog-form">
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?= e($_SESSION["csrf_blog"]) ?>">
                                                        <input type="hidden" name="aksi" value="hapus">
                                                        <input type="hidden" name="id"
                                                            value="<?= (int)$blog["id"] ?>">
                                                        <button class="table-action delete"
                                                            type="submit" title="Hapus blog"
                                                            aria-label="Hapus blog">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:30px">
                                            Belum ada artikel blog. Klik Tambah Blog untuk membuat artikel.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL BLOG -->
    <div class="modal-overlay" id="blogModal">
        <div class="modal-content blog-modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="blogModalTitle">Tambah Blog</h2>
                    <p id="blogModalDescription">Tambahkan artikel baru untuk customer.</p>
                </div>
                <button class="modal-close" type="button"
                    onclick="closeBlogModal()" aria-label="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" id="blogForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION["csrf_blog"]) ?>">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" id="blogId" value="0">

                <div class="form-group">
                    <label for="blogTitle">Judul Blog</label>
                    <input type="text" name="title" id="blogTitle"
                        placeholder="Masukkan judul blog" required>
                </div>

                <div class="form-group">
                    <label for="blogImage">Gambar Blog (opsional)</label>
                    <input type="file" name="image" id="blogImage"
                        accept=".jpg,.jpeg,.png,.webp">
                    <small>Format JPG, PNG, atau WEBP. Maksimal 3 MB.</small>
                    <div id="currentImage"></div>
                </div>

                <div class="form-group">
                    <label for="blogContent">Isi Blog</label>
                    <textarea name="content" id="blogContent" rows="8"
                        placeholder="Tulis isi blog..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="blogStatus">Status</label>
                    <select name="status" id="blogStatus" required>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary"
                        onclick="closeBlogModal()">Batal</button>
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span id="blogSubmitLabel">Simpan Blog</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/admin-pages.js"></script>
    <script>
        const blogModal = document.getElementById("blogModal");
        const blogForm = document.getElementById("blogForm");
        const blogId = document.getElementById("blogId");
        const blogTitle = document.getElementById("blogTitle");
        const blogContent = document.getElementById("blogContent");
        const blogStatus = document.getElementById("blogStatus");
        const blogImage = document.getElementById("blogImage");
        const currentImage = document.getElementById("currentImage");

        function openBlogModal() {
            blogForm.reset();
            blogId.value = "0";
            blogStatus.value = "published";
            document.getElementById("blogModalTitle").textContent = "Tambah Blog";
            document.getElementById("blogModalDescription").textContent =
                "Tambahkan artikel baru untuk customer.";
            document.getElementById("blogSubmitLabel").textContent = "Simpan Blog";
            currentImage.innerHTML = "";

            blogModal.style.display = "flex";
            blogModal.classList.add("show");
            document.body.style.overflow = "hidden";
            blogTitle.focus();
        }

        function closeBlogModal() {
            blogModal.classList.remove("show");
            blogModal.style.display = "none";
            document.body.style.overflow = "";
        }

        function editBlog(id, title, content, status) {
            blogForm.reset();
            blogId.value = id;
            blogTitle.value = title;
            blogContent.value = content;
            blogStatus.value = status;

            document.getElementById("blogModalTitle").textContent = "Edit Blog";
            document.getElementById("blogModalDescription").textContent =
                "Perbarui informasi artikel blog.";
            document.getElementById("blogSubmitLabel").textContent = "Simpan Perubahan";
            currentImage.innerHTML =
                "<small>Gambar lama tetap digunakan jika tidak memilih gambar baru.</small>";

            blogModal.style.display = "flex";
            blogModal.classList.add("show");
            document.body.style.overflow = "hidden";
            blogTitle.focus();
        }

        blogModal.addEventListener("click", function(event) {
            if (event.target === blogModal) closeBlogModal();
        });

        document.addEventListener("keydown", function(event) {
            if (event.key === "Escape" && blogModal.classList.contains("show")) {
                closeBlogModal();
            }
        });

        function filterBlogs() {
            const keyword = document.getElementById("searchBlog").value.toLowerCase().trim();
            const status = document.getElementById("statusFilter").value;

            document.querySelectorAll("#blogTable tr[data-status]").forEach(function(row) {
                const cocokJudul = row.cells[1].textContent.toLowerCase().includes(keyword);
                const cocokStatus = status === "" || row.dataset.status === status;
                row.style.display = cocokJudul && cocokStatus ? "" : "none";
            });
        }

        document.getElementById("searchBlog").addEventListener("input", filterBlogs);
        document.getElementById("statusFilter").addEventListener("change", filterBlogs);

        document.querySelectorAll(".delete-blog-form").forEach(function(form) {
            form.addEventListener("submit", function(event) {
                event.preventDefault();

                Swal.fire({
                    title: "Hapus artikel?",
                    text: "Artikel yang dihapus tidak dapat dikembalikan.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya, hapus",
                    cancelButtonText: "Batal",
                    confirmButtonColor: "#d46a91",
                    cancelButtonColor: "#777"
                }).then(function(result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        <?php if ($alert !== ""): ?>
            Swal.fire({
                icon: <?= json_encode($alertType === "success" ? "success" : "error") ?>,
                title: <?= json_encode($alertType === "success" ? "Berhasil!" : "Gagal!") ?>,
                text: <?= json_encode($alert) ?>,
                confirmButtonText: "OK",
                confirmButtonColor: "#d46a91"
            });
        <?php endif; ?>
    </script>
    <script src="../js/admin-filter-select.js"></script>
</body>

</html>