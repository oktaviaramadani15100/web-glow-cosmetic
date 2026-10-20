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

function rupiah($angka)
{
    return "Rp " . number_format((float)$angka, 0, ",", ".");
}

function monthlyComparison(PDO $conn, string $query, array $parameters): array
{
    $statement = $conn->prepare($query);
    $statement->execute($parameters);

    $result = $statement->fetch(PDO::FETCH_ASSOC);

    return [
        "current" => (float)($result["current_value"] ?? 0),
        "previous" => (float)($result["previous_value"] ?? 0)
    ];
}

function comparisonLabel(float $current, float $previous): array
{
    if ($previous === 0.0) {
        if ($current === 0.0) {
            return [
                "class" => "neutral",
                "icon" => "fa-minus",
                "text" => "Belum ada data bulan ini"
            ];
        }

        return [
            "class" => "increase",
            "icon" => "fa-arrow-up",
            "text" => "Data baru bulan ini"
        ];
    }

    $change = (int)round((($current - $previous) / $previous) * 100);

    if ($change > 0) {
        return [
            "class" => "increase",
            "icon" => "fa-arrow-up",
            "text" => $change . "% dari periode bulan lalu"
        ];
    }

    if ($change < 0) {
        return [
            "class" => "decrease",
            "icon" => "fa-arrow-down",
            "text" => abs($change) . "% dari periode bulan lalu"
        ];
    }

    return [
        "class" => "neutral",
        "icon" => "fa-minus",
        "text" => "Sama dengan periode bulan lalu"
    ];
}

$today = new DateTimeImmutable("today");
$currentMonthStart = $today->modify("first day of this month");
$currentPeriodEnd = $today->modify("+1 day");
$previousMonthStart = $currentMonthStart->modify("-1 month");
$comparisonDays = min(
    (int)$today->format("j"),
    (int)$previousMonthStart->format("t")
);
$previousPeriodEnd = $previousMonthStart->modify("+" . $comparisonDays . " days");

$comparisonParameters = [
    ":current_start" => $currentMonthStart->format("Y-m-d"),
    ":current_end" => $currentPeriodEnd->format("Y-m-d"),
    ":previous_start" => $previousMonthStart->format("Y-m-d"),
    ":previous_end" => $previousPeriodEnd->format("Y-m-d")
];

$totalPenjualan = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE payment_status = 'paid'
      AND order_status <> 'cancelled'
")->fetchColumn();

$totalPesanan = $conn->query("
    SELECT COUNT(*) FROM orders
")->fetchColumn();

$totalPelanggan = $conn->query("
    SELECT COUNT(*) FROM users
    WHERE role = 'customer' AND status = 'active'
")->fetchColumn();

$jumlahStokMenipis = $conn->query("
    SELECT COUNT(*)
    FROM products p
    LEFT JOIN stock s ON s.product_id = p.id
    WHERE p.status = 'active'
      AND COALESCE(s.quantity, 0)
          <= COALESCE(s.minimum_stock, 5)
")->fetchColumn();


$perbandinganPenjualan = monthlyComparison($conn, "
    SELECT
        COALESCE(SUM(CASE
            WHEN order_date >= :current_start
             AND order_date < :current_end
            THEN total_amount ELSE 0
        END), 0) AS current_value,

        COALESCE(SUM(CASE
            WHEN order_date >= :previous_start
             AND order_date < :previous_end
            THEN total_amount ELSE 0
        END), 0) AS previous_value

    FROM orders
    WHERE payment_status = 'paid'
      AND order_status <> 'cancelled'
", $comparisonParameters);


$perbandinganPesanan = monthlyComparison($conn, "
    SELECT
        COUNT(CASE
            WHEN order_date >= :current_start AND order_date < :current_end
            THEN 1
        END) AS current_value,
        COUNT(CASE
            WHEN order_date >= :previous_start AND order_date < :previous_end
            THEN 1
        END) AS previous_value
    FROM orders
", $comparisonParameters);

$perbandinganPelanggan = monthlyComparison($conn, "
    SELECT
        COUNT(CASE
            WHEN created_at >= :current_start AND created_at < :current_end
            THEN 1
        END) AS current_value,
        COUNT(CASE
            WHEN created_at >= :previous_start AND created_at < :previous_end
            THEN 1
        END) AS previous_value
    FROM users
    WHERE role = 'customer'
      AND status = 'active'
", $comparisonParameters);

$labelPenjualan = comparisonLabel(
    $perbandinganPenjualan["current"],
    $perbandinganPenjualan["previous"]
);
$labelPesanan = comparisonLabel(
    $perbandinganPesanan["current"],
    $perbandinganPesanan["previous"]
);
$labelPelanggan = comparisonLabel(
    $perbandinganPelanggan["current"],
    $perbandinganPelanggan["previous"]
);

$produkTerlaris = $conn->query("
    SELECT COALESCE(p.name, oi.product_name) AS name,
           p.image,
           COALESCE(c.name, 'Tanpa kategori') AS category_name,
           SUM(oi.quantity) AS total_terjual
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    LEFT JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE o.payment_status = 'paid'
      AND o.order_status <> 'cancelled'
    GROUP BY oi.product_id, oi.product_name, p.name, p.image, c.name
    ORDER BY total_terjual DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$stokMenipis = $conn->query("
    SELECT p.name,
           p.image,
           COALESCE(c.name, 'Tanpa kategori') AS category_name,
           COALESCE(s.quantity, 0) AS stock_quantity
    FROM products p
    LEFT JOIN stock s ON s.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active'
      AND COALESCE(s.quantity, 0)
          <= COALESCE(s.minimum_stock, 5)
    ORDER BY stock_quantity ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$pesananTerbaru = $conn->query("
    SELECT o.order_number, o.total_amount,
           o.order_status, o.order_date,
           u.name AS customer_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.order_date DESC, o.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);


$dataGrafik = [];

foreach ([7, 30] as $jumlahHari) {
    $stmt = $conn->prepare("
        SELECT DATE(order_date) AS tanggal,
               SUM(total_amount) AS total
        FROM orders
        WHERE payment_status = 'paid'
          AND order_status <> 'cancelled'
          AND order_date >= CURDATE() - INTERVAL " . ($jumlahHari - 1) . " DAY
          AND order_date < CURDATE() + INTERVAL 1 DAY
        GROUP BY DATE(order_date)
    ");

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dataTanggal = [];

    foreach ($rows as $row) {
        $dataTanggal[$row["tanggal"]] = (float)$row["total"];
    }

    $dataGrafik[(string)$jumlahHari] = [];

    for ($i = $jumlahHari - 1; $i >= 0; $i--) {
        $tanggal = date("Y-m-d", strtotime("-" . $i . " days"));

        $dataGrafik[(string)$jumlahHari][] = [
            "label" => date("d/m", strtotime($tanggal)),
            "total" => $dataTanggal[$tanggal] ?? 0
        ];
    }
}

?>




<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - Glow.Cosmetic</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/dashboard.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>


<body>

    <div class="admin-layout">

        <aside class="sidebar" id="sidebar">

            <div class="sidebar-logo">

                <img src="../img/logo2.png" alt="Glow.Cosmetic">

            </div>

            <nav class="sidebar-menu">

                <a href="dashboard.php" class="menu-item active">
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
                            Dashboard
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

            <div class="dashboard-content">

                <section class="stats-grid">

                    <div class="stat-card">

                        <div class="stat-icon green">

                            <i class="fa-solid fa-money-bill-trend-up"></i>

                        </div>

                        <div class="stat-info">

                            <span>
                                Total Penjualan
                            </span>


                            <strong><?= rupiah($totalPenjualan) ?></strong>


                            <small class="<?= $labelPenjualan["class"] ?>">
                                <i class="fa-solid <?= $labelPenjualan["icon"] ?>"></i>
                                <?= htmlspecialchars($labelPenjualan["text"], ENT_QUOTES, "UTF-8") ?>

                            </small>

                        </div>

                    </div>

                    <div class="stat-card">

                        <div class="stat-icon red">

                            <i class="fa-solid fa-receipt"></i>

                        </div>

                        <div class="stat-info">

                            <span>
                                Total Pesanan
                            </span>

                            <strong>
                                <?= number_format((int)$totalPesanan, 0, ",", ".") ?>
                            </strong>

                            <small class="<?= $labelPesanan["class"] ?>">
                                <i class="fa-solid <?= $labelPesanan["icon"] ?>"></i>
                                <?= htmlspecialchars($labelPesanan["text"], ENT_QUOTES, "UTF-8") ?>

                            </small>

                        </div>

                    </div>

                    <div class="stat-card">

                        <div class="stat-icon pink">

                            <i class="fa-solid fa-users"></i>

                        </div>

                        <div class="stat-info">

                            <span>
                                Total Pelanggan
                            </span>

                            <strong>
                                <?= number_format((int)$totalPelanggan, 0, ",", ".") ?>
                            </strong>

                            <small class="<?= $labelPelanggan["class"] ?>">
                                <i class="fa-solid <?= $labelPelanggan["icon"] ?>"></i>
                                <?= htmlspecialchars($labelPelanggan["text"], ENT_QUOTES, "UTF-8") ?>

                            </small>

                        </div>

                    </div>



                    <!-- STOK -->

                    <div class="stat-card">

                        <div class="stat-icon purple">

                            <i class="fa-solid fa-box-open"></i>

                        </div>

                        <div class="stat-info">

                            <span>
                                Stok Menipis
                            </span>

                            <strong>
                                <?= number_format((int)$jumlahStokMenipis, 0, ",", ".") ?> Produk
                            </strong>

                            <small class="<?= (int)$jumlahStokMenipis > 0 ? "warning" : "increase" ?>">
                                <i class="fa-solid <?= (int)$jumlahStokMenipis > 0 ? "fa-triangle-exclamation" : "fa-circle-check" ?>"></i>
                                <?= (int)$jumlahStokMenipis > 0 ? "Perlu perhatian" : "Stok aman" ?>

                            </small>

                        </div>

                    </div>


                </section>

                <section class="dashboard-row">

                    <div class="dashboard-card sales-chart-card">

                        <div class="card-header">

                            <div>

                                <h2>
                                    Grafik Penjualan
                                </h2>

                                <p>
                                    Performa penjualan 7 hari terakhir
                                </p>

                            </div>


                            <select id="chartPeriod">

                                <option value="7">
                                    7 Hari Terakhir
                                </option>

                                <option value="30">
                                    30 Hari Terakhir
                                </option>

                            </select>

                        </div>


                        <div class="chart-wrapper">

                            <canvas id="salesChart"></canvas>

                        </div>

                    </div>

                    <div class="dashboard-card top-products-card">

                        <div class="card-header">

                            <div>

                                <h2>
                                    Produk Terlaris
                                </h2>

                                <p>
                                    Produk dengan penjualan tertinggi
                                </p>

                            </div>

                        </div>


                        <div class="product-ranking">

                            <?php if (!empty($produkTerlaris)): ?>
                                <?php foreach ($produkTerlaris as $i => $produk): ?>
                                    <?php
                                    $gambarProduk = str_replace(
                                        "\\",
                                        "/",
                                        trim((string)($produk["image"] ?? ""))
                                    );
                                    $gambarProdukUrl = $gambarProduk !== ""
                                        ? "../" . ltrim($gambarProduk, "/")
                                        : "";
                                    ?>
                                    <div class="ranking-item">
                                        <span class="ranking-number">
                                            <?= $i + 1 ?>
                                        </span>

                                        <div class="ranking-image">
                                            <?php if ($gambarProdukUrl !== ""): ?>
                                                <img
                                                    src="<?= htmlspecialchars($gambarProdukUrl, ENT_QUOTES, "UTF-8") ?>"
                                                    alt="<?= htmlspecialchars($produk["name"], ENT_QUOTES, "UTF-8") ?>">
                                            <?php else: ?>
                                                <i class="fa-solid fa-image" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="ranking-info">
                                            <strong>
                                                <?= htmlspecialchars($produk["name"], ENT_QUOTES, "UTF-8") ?>
                                            </strong>
                                            <span>
                                                <?= htmlspecialchars($produk["category_name"] ?? "Tanpa kategori", ENT_QUOTES, "UTF-8") ?>
                                            </span>
                                        </div>

                                        <div class="ranking-sales">
                                            <strong><?= number_format((int)$produk["total_terjual"], 0, ",", ".") ?></strong>
                                            <span>terjual</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Belum ada data produk terlaris.</p>
                            <?php endif; ?>


                        </div>

                    </div>


                </section>

                <section class="dashboard-row">

                    <div class="dashboard-card orders-card">

                        <div class="card-header">

                            <div>

                                <h2>
                                    Pesanan Terbaru
                                </h2>

                                <p>
                                    Pesanan yang baru masuk
                                </p>

                            </div>

                        </div>


                        <div class="table-wrapper">

                            <table>

                                <thead>

                                    <tr>

                                        <th>
                                            No. Pesanan
                                        </th>

                                        <th>
                                            Pelanggan
                                        </th>

                                        <th>
                                            Total
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Tanggal
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php if (!empty($pesananTerbaru)): ?>
                                        <?php foreach ($pesananTerbaru as $pesanan): ?>
                                            <?php
                                            $status = $pesanan["order_status"];

                                            $statusLabel = [
                                                "pending" => "Menunggu",
                                                "processing" => "Diproses",
                                                "shipped" => "Dikirim",
                                                "completed" => "Selesai",
                                                "cancelled" => "Dibatalkan"
                                            ];

                                            $statusClass = [
                                                "pending" => "process",
                                                "processing" => "process",
                                                "shipped" => "shipping",
                                                "completed" => "success",
                                                "cancelled" => "process"
                                            ];
                                            ?>

                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($pesanan["order_number"] ?? "-", ENT_QUOTES, "UTF-8") ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars($pesanan["customer_name"], ENT_QUOTES, "UTF-8") ?>
                                                </td>

                                                <td><?= rupiah($pesanan["total_amount"]) ?></td>

                                                <td>
                                                    <span class="status <?= $statusClass[$status] ?? "process" ?>">
                                                        <?= $statusLabel[$status] ?? htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?= date("d M Y", strtotime($pesanan["order_date"])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5">Belum ada pesanan.</td>
                                        </tr>
                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                    <div class="dashboard-card stock-card">

                        <div class="card-header">

                            <div>

                                <h2>
                                    Stok Menipis
                                </h2>

                                <p>
                                    Produk yang perlu restock
                                </p>

                            </div>

                        </div>


                        <div class="stock-list">

                            <?php if (!empty($stokMenipis)): ?>
                                <?php foreach ($stokMenipis as $i => $produk): ?>
                                    <?php
                                    $gambarProduk = str_replace(
                                        "\\",
                                        "/",
                                        trim((string)($produk["image"] ?? ""))
                                    );
                                    $gambarProdukUrl = $gambarProduk !== ""
                                        ? "../" . ltrim($gambarProduk, "/")
                                        : "";
                                    $jumlahStok = (int)$produk["stock_quantity"];
                                    ?>
                                    <div class="stock-item">
                                        <span class="stock-number"><?= $i + 1 ?></span>

                                        <div class="stock-image">
                                            <?php if ($gambarProdukUrl !== ""): ?>
                                                <img
                                                    src="<?= htmlspecialchars($gambarProdukUrl, ENT_QUOTES, "UTF-8") ?>"
                                                    alt="<?= htmlspecialchars($produk["name"], ENT_QUOTES, "UTF-8") ?>">
                                            <?php else: ?>
                                                <i class="fa-solid fa-image" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="stock-info">
                                            <strong>
                                                <?= htmlspecialchars($produk["name"], ENT_QUOTES, "UTF-8") ?>
                                            </strong>
                                            <span>
                                                <?= htmlspecialchars($produk["category_name"], ENT_QUOTES, "UTF-8") ?>
                                            </span>
                                        </div>

                                        <div class="stock-quantity<?= $jumlahStok === 0 ? " out-of-stock" : "" ?>">
                                            <strong><?= number_format($jumlahStok, 0, ",", ".") ?></strong>
                                            <span>unit tersisa</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Belum ada data stok menipis.</p>
                            <?php endif; ?>


                        </div>

                    </div>


                </section>


            </div>


        </main>


    </div>


    <script>
        window.dashboardData = <?= json_encode(
                                    $dataGrafik,
                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                                ) ?>;
    </script>

    <script src="../js/dashboard.js"></script>

</body>

</html>