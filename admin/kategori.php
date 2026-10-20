<?php
session_start();

require_once __DIR__ . "/../config/database.php";

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

$stmt = $conn->query("
    SELECT id, name, description, status, created_at
    FROM categories
    ORDER BY id DESC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kategori - Dashboard Admin Glow.Cosmetic</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/kategori.css">
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

                <a href="kategori.php" class="menu-item active">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Kategori</span>
                </a>

                <a href="stok.php" class="menu-item ">
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
                    <i class="fas fa-users"></i>

                    <span>
                        Users
                    </span>
                </a>

                <a href="laporan.php" class="menu-item">
                    <i class="fa-solid fa-chart-column"></i>
                    <span>Laporan</span>
                </a>

                <a href="faq.php" class="menu-item">
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

                    <button class="mobile-menu-btn" id="menuButton">

                        <i class="fa-solid fa-bars"></i>

                    </button>

                    <div>
                        <span class="breadcrumb">Manajemen</span>
                        <h1>
                            Kategori
                        </h1>

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

                <div class="page-heading">

                    <div>

                        <h2>
                            Manajemen Kategori
                        </h2>

                        <p>
                            Kelola kategori produk Glow.Cosmetic.
                        </p>

                    </div>


                    <button class="add-button" onclick="openCategoryModal()">

                        <i class="fa-solid fa-plus"></i>

                        Tambah Kategori

                    </button>

                </div>

                <div class="modal-overlay" id="categoryModal">
                    <div class="modal">

                        <div class="modal-header">
                            <div>
                                <h3 id="modalTitle">Tambah Kategori</h3>
                                <p>Isi informasi kategori produk Glow.Cosmetic.</p>
                            </div>

                            <button type="button" class="close-modal"
                                onclick="closeCategoryModal()">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <form id="categoryForm">
                            <input type="hidden" id="categoryId" name="id">

                            <div class="form-group">
                                <label for="categoryName">Nama Kategori</label>
                                <input
                                    type="text"
                                    id="categoryName"
                                    name="name"
                                    placeholder="Contoh: Skincare"
                                    maxlength="100"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="categoryDescription">Deskripsi</label>
                                <textarea
                                    id="categoryDescription"
                                    name="description"
                                    rows="3"
                                    placeholder="Masukkan deskripsi kategori"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="categoryStatus">Status</label>
                                <select id="categoryStatus" name="status">
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>
                            </div>

                            <div class="modal-actions">
                                <button
                                    type="button"
                                    class="cancel-button"
                                    onclick="closeCategoryModal()">
                                    Batal
                                </button>

                                <button type="submit" class="save-button">
                                    <i class="fa-solid fa-check"></i>
                                    Simpan
                                </button>
                            </div>
                        </form>

                    </div>
                </div>


                <div class="filter-card">

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input type="text" id="searchCategory" placeholder="Cari kategori...">

                    </div>


                    <select id="statusFilter" data-filter-select aria-label="Filter status kategori">

                        <option value="all">
                            Semua Status
                        </option>

                        <option value="Aktif">
                            Aktif
                        </option>

                        <option value="Nonaktif">
                            Nonaktif
                        </option>

                    </select>

                </div>

                <div class="category-card">

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th width="6%">
                                        No
                                    </th>

                                    <th>
                                        Nama Kategori
                                    </th>

                                    <th>
                                        Deskripsi
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th width="15%">
                                        Aksi
                                    </th>

                                </tr>

                            </thead>



                            <tbody id="categoryTableBody">
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;padding:25px">
                                            Belum ada kategori.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $index => $category): ?>
                                        <?php
                                        $status = strtolower($category["status"] ?? "active");
                                        $isActive = in_array($status, ["active", "aktif"], true);
                                        $displayStatus = $isActive ? "Aktif" : "Nonaktif";
                                        ?>
                                        <tr
                                            data-id="<?= (int) $category["id"] ?>"
                                            data-name="<?= htmlspecialchars($category["name"], ENT_QUOTES, "UTF-8") ?>"
                                            data-description="<?= htmlspecialchars($category["description"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                                            data-status="<?= $displayStatus ?>">
                                            <td><?= $index + 1 ?></td>

                                            <td>
                                                <div class="category-name">
                                                    <div class="category-icon pink">
                                                        <i class="fa-solid fa-layer-group"></i>
                                                    </div>
                                                    <strong><?= htmlspecialchars($category["name"], ENT_QUOTES, "UTF-8") ?></strong>
                                                </div>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($category["description"] ?? "-", ENT_QUOTES, "UTF-8") ?>
                                            </td>

                                            <td>
                                                <span class="status <?= $isActive ? "active" : "" ?>">
                                                    <span></span>
                                                    <?= $displayStatus ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div class="action-buttons">
                                                    <button type="button" class="action-btn edit"
                                                        onclick="editCategory(this)" title="Edit">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>

                                                    <button type="button" class="action-btn delete"
                                                        onclick="deleteCategory(this)" title="Hapus">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>


                        </table>

                    </div>


                    <!-- EMPTY -->

                    <div class="empty-table" id="emptyTable" style="display:none;">

                        <i class="fa-solid fa-folder-open"></i>

                        <h3>
                            Kategori tidak ditemukan
                        </h3>

                        <p>
                            Coba gunakan kata pencarian yang berbeda.
                        </p>

                    </div>

                </div>


            </div>

        </main>

    </div>

    <div class="modal-overlay" id="categoryModal">

        <div class="modal">


            <div class="modal-header">

                <div>

                    <h3 id="modalTitle">
                        Tambah Kategori
                    </h3>

                    <p>
                        Isi informasi kategori produk.
                    </p>

                </div>


                <button class="close-modal" onclick="closeCategoryModal()">

                    <i class="fa-solid fa-xmark"></i>

                </button>

            </div>



            <form id="categoryForm">


                <div class="form-group">

                    <label>
                        Nama Kategori
                    </label>

                    <input type="text" id="categoryName" placeholder="Contoh: Skincare" required>

                </div>



                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select id="categoryStatus">

                        <option value="Aktif">
                            Aktif
                        </option>

                        <option value="Nonaktif">
                            Nonaktif
                        </option>

                    </select>

                </div>



                <div class="modal-actions">

                    <button type="button" class="cancel-button" onclick="closeCategoryModal()">

                        Batal

                    </button>


                    <button type="submit" class="save-button">

                        <i class="fa-solid fa-check"></i>

                        Simpan

                    </button>

                </div>


            </form>

        </div>

    </div>



    <script src="../js/kategori.js"></script>
    <script src="../js/admin-filter-select.js"></script>

</body>

</html>