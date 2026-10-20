<?php
session_start();
require_once "../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../customer/login.php");
    exit;
}

if (empty($_SESSION["csrf_faq"])) {
    $_SESSION["csrf_faq"] = bin2hex(random_bytes(32));
}



$alert = "";
$alertType = "";

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

$adminId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? 0;

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


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_faq"], $token)) {
        $alert = "Permintaan tidak valid. Silakan coba lagi.";
        $alertType = "error";
    } else {
        $aksi = $_POST["aksi"] ?? "";

        try {
            if ($aksi === "simpan") {
                $id = (int) ($_POST["id"] ?? 0);
                $question = trim($_POST["question"] ?? "");
                $answer = trim($_POST["answer"] ?? "");
                $status = $_POST["status"] ?? "active";

                if ($question === "" || $answer === "") {
                    throw new Exception("Pertanyaan dan jawaban wajib diisi.");
                }

                if (!in_array($status, ["active", "inactive"], true)) {
                    throw new Exception("Status FAQ tidak valid.");
                }

                if ($id > 0) {
                    $stmt = $conn->prepare(
                        "UPDATE faqs
                            SET question = :question,
                                answer = :answer,
                                status = :status
                            WHERE id = :id"
                    );

                    $stmt->execute([
                        ":question" => $question,
                        ":answer" => $answer,
                        ":status" => $status,
                        ":id" => $id
                    ]);

                    $alert = "FAQ berhasil diperbarui.";
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO faqs
                                (question, answer, status)
                            VALUES
                                (:question, :answer, :status)"
                    );

                    $stmt->execute([
                        ":question" => $question,
                        ":answer" => $answer,
                        ":status" => $status
                    ]);

                    $alert = "FAQ berhasil ditambahkan.";
                }

                $alertType = "success";
            } elseif ($aksi === "hapus") {
                $id = (int) ($_POST["id"] ?? 0);

                if ($id <= 0) {
                    throw new Exception("ID FAQ tidak valid.");
                }

                $stmt = $conn->prepare("DELETE FROM faqs WHERE id = :id");
                $stmt->execute([":id" => $id]);

                $deleted = $stmt->rowCount();

                $alert = $deleted > 0
                    ? "FAQ berhasil dihapus."
                    : "Data FAQ tidak ditemukan.";

                $alertType = $deleted > 0 ? "success" : "error";
            }
        } catch (Exception $e) {
            $alert = $e->getMessage();
            $alertType = "error";
        } catch (PDOException $e) {
            $alert = "Terjadi kesalahan saat menyimpan data FAQ.";
            $alertType = "error";
        }
    }
}

$stmt = $conn->query(
    "SELECT id, question, answer, sort_order, status, created_at
        FROM faqs
        ORDER BY sort_order ASC, id DESC"
);

$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalFAQ = count($faqs);
$totalAktif = 0;
$totalNonaktif = 0;

foreach ($faqs as $faq) {
    if ($faq["status"] === "active") {
        $totalAktif++;
    } else {
        $totalNonaktif++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola FAQ - Admin Glow.Cosmetic</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>

                <a href="produk.php" class="menu-item">
                    <i class="fa-solid fa-box"></i>
                    <span>Produk</span>
                </a>

                <a href="kategori.php" class="menu-item">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Kategori</span>
                </a>

                <a href="stok.php" class="menu-item">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Stok</span>
                </a>

                <a href="pesanan.php" class="menu-item">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Pesanan</span>
                </a>

                <a href="pembayaran.php" class="menu-item">
                    <i class="fa-solid fa-wallet"></i>
                    <span>Pembayaran</span>
                </a>

                <a href="pengiriman.php" class="menu-item">
                    <i class="fa-solid fa-truck"></i>
                    <span>Pengiriman</span>
                </a>

                <a href="user.php" class="menu-item">
                    <i class="fa-solid fa-users"></i>
                    <span>Users</span>
                </a>

                <a href="laporan.php" class="menu-item">
                    <i class="fa-solid fa-chart-column"></i>
                    <span>Laporan</span>
                </a>

                <a href="faq.php" class="menu-item active">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>FAQ</span>
                </a>

                <a href="blog.php" class="menu-item">
                    <i class="fa-solid fa-book-open"></i>
                    <span>Blog</span>
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
                    <button class="mobile-menu-btn" id="menuButton" type="button"
                        aria-label="Buka menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div>
                        <span class="breadcrumb">Manajemen</span>
                        <h1>FAQ</h1>
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

            <section class="faq-content">

                <div class="faq-heading">
                    <div>
                        <h2>Kelola FAQ</h2>
                        <p>Atur pertanyaan dan jawaban untuk membantu pelanggan.</p>
                    </div>

                    <button type="button" class="btn-primary" id="faqAddButton">
                        <i class="fa-solid fa-plus"></i>
                        Tambah FAQ
                    </button>

                </div>

                <div class="faq-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa-solid fa-circle-question"></i>
                        </div>
                        <div>
                            <p>Total FAQ</p>
                            <h3><?= $totalFAQ ?></h3>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <p>FAQ Aktif</p>
                            <h3><?= $totalAktif ?></h3>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa-solid fa-eye-slash"></i>
                        </div>
                        <div>
                            <p>FAQ Nonaktif</p>
                            <h3><?= $totalNonaktif ?></h3>
                        </div>
                    </div>
                </div>

                <div class="faq-toolbar">
                    <div class="faq-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchFAQ"
                            placeholder="Cari pertanyaan atau jawaban...">
                    </div>

                    <select id="statusFilter" data-filter-select aria-label="Filter status FAQ">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>

                <div class="faq-table-card">
                    <table class="faq-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Pertanyaan</th>
                                <th>Jawaban</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody id="faqTable">
                            <?php if (count($faqs) > 0): ?>
                                <?php foreach ($faqs as $index => $faq): ?>
                                    <tr data-status="<?= e($faq["status"]) ?>">
                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <div class="faq-question">
                                                <?= e($faq["question"]) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="faq-answer">
                                                <?= e(mb_strimwidth(
                                                    $faq["answer"],
                                                    0,
                                                    140,
                                                    "..."
                                                )) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if ($faq["status"] === "active"): ?>
                                                <span class="status-badge status-active">
                                                    Aktif
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive">
                                                    Nonaktif
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-edit"
                                                    onclick='editFAQ(
                                                <?= json_encode(
                                                    $faq["id"],
                                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                                ) ?>,
                                                <?= json_encode(
                                                    $faq["question"],
                                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                                ) ?>,
                                                <?= json_encode(
                                                    $faq["answer"],
                                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                                ) ?>,
                                                <?= json_encode(
                                                    $faq["status"],
                                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                                ) ?>
                                            )'>
                                                    <i class="fa-solid fa-pen"></i>

                                                </button>

                                                <form method="POST"
                                                    onsubmit="return confirm('Yakin ingin menghapus FAQ ini?')">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= e($_SESSION["csrf_faq"]) ?>">
                                                    <input type="hidden" name="aksi" value="hapus">
                                                    <input type="hidden" name="id"
                                                        value="<?= (int) $faq["id"] ?>">

                                                    <button type="submit" class="btn-delete">
                                                        <i class="fa-solid fa-trash"></i>

                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="fa-regular fa-folder-open"></i>
                                        Belum ada FAQ. Klik Tambah FAQ untuk membuatnya.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="faqModal">
        <div class="faq-modal">
            <div class="modal-header">
                <div>
                    <h2 id="modalTitle">Tambah FAQ</h2>
                    <p>Isi pertanyaan dan jawaban yang ingin ditampilkan.</p>
                </div>

                <button type="button"
                    class="modal-close"
                    data-close-faq-modal
                    aria-label="Tutup">
                    &times;
                </button>
            </div>


            <form method="POST" id="faqForm">
                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION["csrf_faq"]) ?>">

                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" id="faqId" value="0">

                <div class="form-group">
                    <label for="question">Pertanyaan</label>
                    <input
                        type="text"
                        name="question"
                        id="question"
                        placeholder="Masukkan pertanyaan FAQ"
                        required>
                </div>

                <div class="form-group">
                    <label for="answer">Jawaban</label>
                    <textarea
                        name="answer"
                        id="answer"
                        rows="5"
                        placeholder="Masukkan jawaban FAQ"
                        required></textarea>
                </div>

                <div class="form-group">
                    <label for="faqStatus">Status</label>
                    <select name="status" id="faqStatus" required>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button"
                        class="btn-secondary"
                        data-close-faq-modal>
                        Batal
                    </button>

                    <button type="submit" class="btn-primary">
                        Simpan FAQ
                    </button>
                </div>
            </form>

        </div>
    </div>


    <script src="../js/admin-filter-select.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const faqModal = document.getElementById("faqModal");
            const faqForm = document.getElementById("faqForm");
            const faqAddButton = document.getElementById("faqAddButton");

            const faqId = document.getElementById("faqId");
            const questionInput = document.getElementById("question");
            const answerInput = document.getElementById("answer");
            const statusInput = document.getElementById("faqStatus");
            const modalTitle = document.getElementById("modalTitle");

            const searchFAQ = document.getElementById("searchFAQ");
            const statusFilter = document.getElementById("statusFilter");

            // Buka modal
            function openFAQForm() {
                if (!faqModal || !faqForm) {
                    alert("Modal atau form FAQ tidak ditemukan. Periksa ID HTML.");
                    return;
                }

                faqForm.reset();
                faqId.value = "0";
                questionInput.value = "";
                answerInput.value = "";
                statusInput.value = "active";
                modalTitle.textContent = "Tambah FAQ";

                faqModal.style.display = "flex";
                faqModal.classList.add("show");
                document.body.style.overflow = "hidden";

                questionInput.focus();
                console.log("Modal Tambah FAQ berhasil dibuka");
            }

            // Tutup modal
            function closeFAQForm() {
                faqModal.classList.remove("show");
                faqModal.style.display = "none";
                document.body.style.overflow = "";
            }


            if (faqAddButton) {
                faqAddButton.onclick = function() {
                    console.log("Tombol Tambah FAQ diklik");

                    faqForm.reset();
                    faqId.value = "0";
                    questionInput.value = "";
                    answerInput.value = "";
                    statusInput.value = "active";
                    modalTitle.textContent = "Tambah FAQ";

                    faqModal.style.display = "flex";
                    faqModal.classList.add("show");
                    document.body.style.overflow = "hidden";

                    questionInput.focus();
                };
            } else {
                console.error("Tombol faqAddButton tidak ditemukan");
            }


            // Fungsi edit FAQ
            window.editFAQ = function(id, question, answer, status) {
                faqForm.reset();

                faqId.value = id;
                questionInput.value = question;
                answerInput.value = answer;
                statusInput.value = status;
                modalTitle.textContent = "Edit FAQ";

                faqModal.style.display = "flex";
                faqModal.classList.add("show");
                document.body.style.overflow = "hidden";

                questionInput.focus();
            };

            // Event semua tombol tutup
            document.querySelectorAll("[data-close-faq-modal]").forEach(function(button) {
                button.addEventListener("click", closeFAQForm);
            });

            // Klik area luar modal
            faqModal.addEventListener("click", function(event) {
                if (event.target === faqModal) {
                    closeFAQForm();
                }
            });

            // Tombol Escape
            document.addEventListener("keydown", function(event) {
                if (event.key === "Escape" && faqModal.classList.contains("show")) {
                    closeFAQForm();
                }
            });

            // Pencarian dan filter
            function filterFAQ() {
                const keyword = searchFAQ.value.toLowerCase().trim();
                const selectedStatus = statusFilter.value;

                document.querySelectorAll("#faqTable tr[data-status]").forEach(function(row) {
                    const cocokKata = row.textContent.toLowerCase().includes(keyword);
                    const cocokStatus =
                        selectedStatus === "" ||
                        row.dataset.status === selectedStatus;

                    row.style.display = cocokKata && cocokStatus ? "" : "none";
                });
            }

            searchFAQ.addEventListener("input", filterFAQ);
            statusFilter.addEventListener("change", filterFAQ);

            console.log("Script FAQ berhasil dimuat");
        });
    </script>

    <script>
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


</body>

</html>