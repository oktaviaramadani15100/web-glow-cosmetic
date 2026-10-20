<?php
session_start();

require_once "../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
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

$summaryQuery = $conn->query("
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(COALESCE(s.quantity, 0)), 0) AS total_stock,
        COALESCE(SUM(
            CASE
                WHEN COALESCE(s.quantity, 0) > 0
                 AND COALESCE(s.quantity, 0)
                     <= COALESCE(s.minimum_stock, 5)
                THEN 1 ELSE 0
            END
        ), 0) AS low_stock,
        COALESCE(SUM(
            CASE
                WHEN COALESCE(s.quantity, 0) = 0
                THEN 1 ELSE 0
            END
        ), 0) AS out_stock
    FROM products p
    LEFT JOIN stock s ON s.product_id = p.id
    WHERE p.status = 'active'
");

$summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

$productQuery = $conn->query("
    SELECT
        p.id,
        p.name,
        p.sku,
        p.image,
        COALESCE(c.name, 'Tanpa kategori') AS category_name,
        COALESCE(s.quantity, 0) AS quantity,
        COALESCE(s.minimum_stock, 5) AS minimum_stock,
        s.updated_at
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN stock s ON s.product_id = p.id
    WHERE p.status = 'active'
    ORDER BY p.name ASC
");

$products = $productQuery->fetchAll(PDO::FETCH_ASSOC);

function e($value)
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function stockStatus($quantity, $minimum)
{
    if ((int)$quantity === 0) {
        return ["empty", "Habis"];
    }

    if ((int)$quantity <= (int)$minimum) {
        return ["low", "Menipis"];
    }

    return ["available", "Tersedia"];
}

function formatStockDate($date)
{
    if (!$date) {
        return "-";
    }

    return date("d M Y", strtotime($date));
}
?>


<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Stok - Glow.Cosmetic</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/stok.css">
    <link rel="stylesheet" href="../css/admin-filter-select.css">

</head>


<body>

    <div class="admin-layout">

        <aside class="sidebar">

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

                <a href="stok.php" class="menu-item active">
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
                            Stok Produk
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

                <div class="page-title">

                    <div>

                        <span class="page-label">
                            MANAJEMEN PERSEDIAAN
                        </span>

                        <p>
                            Kelola jumlah stok produk Glow.Cosmetic.
                        </p>

                    </div>


                    <!-- <button class="add-stock-btn" onclick="openStockModal()">

                        <i class="fa-solid fa-plus"></i>

                        Tambah Stok

                    </button> -->

                </div>

                <section class="stock-summary">

                    <div class="summary-card">

                        <div class="summary-icon pink">
                            <i class="fa-solid fa-box"></i>
                        </div>

                        <div>

                            <span>Total Produk</span>

                            <strong id="totalProducts">
                                <?= (int)$summary["total_products"] ?>
                            </strong>

                            <small>
                                Produk terdaftar
                            </small>

                        </div>

                    </div>


                    <div class="summary-card">

                        <div class="summary-icon green">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>

                        <div>

                            <span>Total Stok</span>

                            <strong id="totalStock">
                                <?= (int)$summary["total_stock"] ?>
                            </strong>

                            <small>
                                Unit tersedia
                            </small>

                        </div>

                    </div>


                    <div class="summary-card">

                        <div class="summary-icon orange">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>

                        <div>

                            <span>Stok Menipis</span>

                            <strong id="lowStock">
                                <?= (int)$summary["low_stock"] ?>
                            </strong>

                            <small>
                                Perlu diperhatikan
                            </small>

                        </div>

                    </div>


                    <div class="summary-card">

                        <div class="summary-icon red">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>

                        <div>

                            <span>Stok Habis</span>

                            <strong id="outStock">
                                <?= (int)$summary["out_stock"] ?>
                            </strong>

                            <small>
                                Produk kosong
                            </small>

                        </div>

                    </div>


                </section>

                <section class="stock-card">

                    <div class="stock-card-header">

                        <div>

                            <h3>
                                Daftar Stok Produk
                            </h3>

                            <p>
                                Informasi persediaan produk saat ini.
                            </p>

                        </div>


                        <div class="table-actions">

                            <div class="search-box">

                                <i class="fa-solid fa-magnifying-glass"></i>

                                <input type="text" id="searchStock" placeholder="Cari produk...">

                            </div>


                            <select id="stockFilter" data-filter-select aria-label="Filter status stok">

                                <option value="all">
                                    Semua Status
                                </option>

                                <option value="available">
                                    Tersedia
                                </option>

                                <option value="low">
                                    Stok Menipis
                                </option>

                                <option value="empty">
                                    Habis
                                </option>

                            </select>

                        </div>

                    </div>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>No</th>

                                    <th>Produk</th>

                                    <th>Kategori</th>

                                    <th>Stok</th>

                                    <th>Minimum</th>

                                    <th>Status</th>

                                    <th>Terakhir Update</th>

                                    <th>Aksi</th>

                                </tr>

                            </thead>


                            <tbody id="stockTable">
                                <?php foreach ($products as $index => $product): ?>
                                    <?php
                                    [$statusClass, $statusLabel] = stockStatus(
                                        $product["quantity"],
                                        $product["minimum_stock"]
                                    );

                                    $storedImagePath = str_replace(
                                        "\\",
                                        "/",
                                        trim((string)($product["image"] ?? ""))
                                    );
                                    $imagePath = $storedImagePath !== ""
                                        ? "../" . ltrim($storedImagePath, "/")
                                        : "../img/logo2.png";
                                    ?>

                                    <tr data-status="<?= e($statusClass) ?>">
                                        <td><?= str_pad($index + 1, 2, "0", STR_PAD_LEFT) ?></td>

                                        <td>
                                            <div class="product-cell">
                                                <div class="product-thumb">
                                                    <img
                                                        src="<?= e($imagePath) ?>"
                                                        alt="<?= e($product["name"]) ?>">
                                                </div>

                                                <div>
                                                    <strong><?= e($product["name"]) ?></strong>
                                                    <span>SKU: <?= e($product["sku"] ?? "-") ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <td><?= e($product["category_name"]) ?></td>

                                        <td>
                                            <strong class="stock-number <?= e($statusClass) ?>">
                                                <?= (int)$product["quantity"] ?>
                                            </strong>
                                        </td>

                                        <td><?= (int)$product["minimum_stock"] ?></td>

                                        <td>
                                            <span class="stock-status <?= e($statusClass) ?>">
                                                <i class="fa-solid fa-circle"></i>
                                                <?= e($statusLabel) ?>
                                            </span>
                                        </td>

                                        <td><?= e(formatStockDate($product["updated_at"])) ?></td>

                                        <td>
                                            <button
                                                type="button"
                                                class="action-btn edit"
                                                onclick="editStock(
                                            <?= (int)$product["id"] ?>,
                                            <?= htmlspecialchars(
                                                json_encode($product["name"]),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>,
                                            <?= (int)$product["quantity"] ?>,
                                            <?= (int)$product["minimum_stock"] ?>
                                                )"
                                                title="Ubah Stok">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        </table>

                    </div>

                </section>


            </div>

        </main>

    </div>

    <div class="modal" id="stockModal">

        <div class="modal-content">


            <div class="modal-header">

                <div>

                    <span>
                        MANAJEMEN STOK
                    </span>

                    <h3 id="modalTitle">
                        Tambah Stok
                    </h3>

                </div>


                <button class="close-modal" onclick="closeStockModal()">

                    <i class="fa-solid fa-xmark"></i>

                </button>

            </div>



            <form id="stockForm">


                <div class="form-group">

                    <label>
                        Produk
                    </label>

                    <select id="productName" required>
                        <option value="">Pilih produk</option>

                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int)$product["id"] ?>">
                                <?= e($product["name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label>
                            Jumlah Stok
                        </label>

                        <input type="number" id="stockAmount" min="0" placeholder="Contoh: 20" required>

                    </div>


                    <div class="form-group">

                        <label>
                            Minimum Stok
                        </label>

                        <input type="number" id="minimumStock" min="0" value="10" required>

                    </div>

                </div>



                <div class="form-group">

                    <label>
                        Keterangan
                    </label>

                    <textarea id="stockNote" rows="3" placeholder="Contoh: Penambahan stok dari supplier..."></textarea>

                </div>



                <div class="modal-footer">

                    <button type="button" class="cancel-btn" onclick="closeStockModal()">
                        Batal
                    </button>

                    <button type="submit" class="save-btn">

                        <i class="fa-solid fa-check"></i>

                        Simpan

                    </button>

                </div>


            </form>

        </div>

    </div>



    <script src="../js/stok.js"></script>
    <script src="../js/admin-filter-select.js"></script>

</body>

</html>