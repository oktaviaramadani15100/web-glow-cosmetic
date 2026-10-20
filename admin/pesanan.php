<?php
session_start();

require_once "../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
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

function orderStatus($status)
{
    $statuses = [
        "pending" => ["Pending", "pending"],
        "processing" => ["Diproses", "process"],
        "shipped" => ["Dikirim", "shipping"],
        "completed" => ["Selesai", "success"],
        "cancelled" => ["Dibatalkan", "cancelled"]
    ];

    return $statuses[$status]
        ?? [ucfirst((string)$status), "pending"];
}

try {
    $query = $conn->prepare("
        SELECT
            o.id,
            o.order_number,
            o.order_date,
            o.total_amount,
            o.order_status,
            o.payment_status,
            u.name AS customer_name
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        ORDER BY o.order_date DESC, o.id DESC
    ");

    $query->execute();
    $orders = $query->fetchAll(PDO::FETCH_ASSOC);

    $dbError = false;
} catch (PDOException $error) {
    error_log($error->getMessage());

    $orders = [];
    $dbError = true;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Pesanan - Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/admin-pages.css">
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

                <a href="stok.php" class="menu-item ">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Stok</span>
                </a>

                <a href="pesanan.php" class="menu-item active">
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
                        <h1>Pesanan</h1>
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

                    <div class="page-toolbar">

                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input
                                type="text"
                                id="searchOrder"
                                placeholder="Cari nomor pesanan atau customer...">
                        </div>

                    </div>

                    <?php if ($dbError): ?>

                        <p role="alert">
                            Gagal mengambil data pesanan. Periksa koneksi
                            database dan struktur tabel.
                        </p>

                    <?php else: ?>

                        <div class="table-wrapper">

                            <table class="admin-table">

                                <thead>
                                    <tr>
                                        <th>NO. PESANAN</th>
                                        <th>CUSTOMER</th>
                                        <th>TANGGAL</th>
                                        <th>TOTAL</th>
                                        <th>STATUS</th>
                                        <th>AKSI</th>
                                    </tr>
                                </thead>

                                <tbody id="orderTableBody">

                                    <?php if (empty($orders)): ?>

                                        <tr>
                                            <td colspan="5" style="text-align:center;">
                                                Belum ada pesanan.
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($orders as $order): ?>
                                            <?php
                                            [$statusText, $statusClass] =
                                                orderStatus($order["order_status"]);
                                            ?>

                                            <tr>
                                                <td><?= e($order["order_number"]) ?></td>

                                                <td>
                                                    <?= e($order["customer_name"] ?? "Customer tidak ditemukan") ?>
                                                </td>

                                                <td>
                                                    <?= !empty($order["order_date"])
                                                        ? e(date("d M Y", strtotime($order["order_date"])))
                                                        : "-" ?>
                                                </td>

                                                <td>
                                                    Rp <?= number_format(
                                                            (float) $order["total_amount"],
                                                            0,
                                                            ",",
                                                            "."
                                                        ) ?>
                                                </td>

                                                <td>
                                                    <span class="status <?= e($statusClass) ?>">
                                                        <?= e($statusText) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <form action="../process/order_status_update.php"
                                                        method="POST"
                                                        style="display:flex; gap:8px; align-items:center; min-width:210px;">

                                                        <input type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e($_SESSION["csrf_token"]) ?>">

                                                        <input type="hidden"
                                                            name="id"
                                                            value="<?= (int) $order["id"] ?>">

                                                        <select name="order_status"
                                                            aria-label="Status pesanan"
                                                            required>
                                                            <?php
                                                            $availableStatuses = [
                                                                "pending" => "Pending",
                                                                "processing" => "Diproses",
                                                                "shipped" => "Dikirim",
                                                                "completed" => "Selesai",
                                                                "cancelled" => "Dibatalkan"
                                                            ];
                                                            ?>

                                                            <?php foreach ($availableStatuses as $value => $label): ?>
                                                                <option value="<?= e($value) ?>"
                                                                    <?= $order["order_status"] === $value
                                                                        ? "selected"
                                                                        : "" ?>>
                                                                    <?= e($label) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                        <button type="submit" class="btn btn-primary">
                                                            Simpan
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </main>

    </div>

    <script src="../js/admin-pages.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById("searchOrder");
            const rows = document.querySelectorAll("#orderTableBody tr");

            if (!searchInput) return;

            searchInput.addEventListener("input", function() {
                const keyword = this.value.toLowerCase().trim();

                rows.forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(keyword) ? "" : "none";
                });
            });

            const params = new URLSearchParams(window.location.search);

            if (params.get("success") === "updated") {
                Swal.fire({
                    icon: "success",
                    title: "Berhasil!",
                    text: "Status pesanan berhasil diperbarui.",
                    confirmButtonText: "OK",
                    confirmButtonColor: "#8b1e4b"
                }).then(() => {
                    window.history.replaceState({},
                        document.title,
                        window.location.pathname
                    );
                });
            } else if (params.has("error")) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal!",
                    text: "Status pesanan gagal diperbarui. Silakan coba lagi.",
                    confirmButtonText: "OK",
                    confirmButtonColor: "#8b1e4b"
                }).then(() => {
                    window.history.replaceState({},
                        document.title,
                        window.location.pathname
                    );
                });
            }
        });
    </script>

</body>

</html>