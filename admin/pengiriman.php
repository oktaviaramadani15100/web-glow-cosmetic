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

function shippingStatus($status)
{
    $statuses = [
        "waiting" => ["Menunggu Pengiriman", "pending"],
        "processing" => ["Sedang Diproses", "process"],
        "shipped" => ["Dalam Perjalanan", "shipping"],
        "delivered" => ["Selesai", "success"],
        "failed" => ["Pengiriman Gagal", "cancelled"]
    ];

    return $statuses[$status]
        ?? ["Belum Ada Data Pengiriman", "pending"];
}

try {
    $query = $conn->prepare("
    SELECT
        o.id AS order_id,
        o.order_number,
        o.order_date,
        o.order_status,
        ua.recipient_name,
        ua.phone,
        ua.address,
        ua.city,
        ua.province,
        ua.postal_code,
        s.id AS shipment_id,
        s.tracking_number,
        s.shipping_cost,
        s.status AS shipping_status,
        s.shipped_at,
        s.delivered_at,
        sm.name AS courier_name
    FROM orders o
    LEFT JOIN user_addresses ua
        ON ua.id = o.address_id
    LEFT JOIN shipments s
        ON s.order_id = o.id
    LEFT JOIN shipping_methods sm
        ON sm.id = s.shipping_method_id
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

    <title>Manajemen Pengiriman - Glow.Cosmetic</title>

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

                <a href="pesanan.php" class="menu-item">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Pesanan</span>
                </a>

                <a href="pembayaran.php" class="menu-item">
                    <i class="fa-solid fa-wallet"></i>
                    <span>Pembayaran</span>
                </a>

                <a href="pengiriman.php" class="menu-item active">
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
                        <h1>Pengiriman</h1>
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
                                id="searchShipping"
                                placeholder="Cari nomor pesanan atau resi...">
                        </div>

                    </div>

                    <?php if ($dbError): ?>

                        <p role="alert">
                            Gagal mengambil data pengiriman.
                            Periksa koneksi database.
                        </p>

                    <?php else: ?>

                        <div class="table-wrapper">

                            <table class="admin-table">

                                <thead>
                                    <tr>
                                        <th>NO. PESANAN</th>
                                        <th>KURIR</th>
                                        <th>NO. RESI</th>
                                        <th>ALAMAT</th>
                                        <th>STATUS</th>
                                        <th>AKSI</th>
                                    </tr>
                                </thead>

                                <tbody id="shippingTableBody">

                                    <?php if (empty($orders)): ?>

                                        <tr>
                                            <td colspan="6" style="text-align:center;">
                                                Belum ada pesanan untuk ditampilkan.
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($orders as $order): ?>
                                            <?php
                                            [$statusText, $statusClass] =
                                                shippingStatus($order["shipping_status"]);
                                            ?>


                                            <tr>
                                                <td>
                                                    <?= e($order["order_number"]) ?>
                                                </td>

                                                <td>
                                                    <?= e($order["courier_name"] ?: "Belum ditentukan") ?>
                                                </td>

                                                <td>
                                                    <?= e($order["tracking_number"] ?: "Belum tersedia") ?>
                                                </td>

                                                <td>
                                                    <?= e($order["recipient_name"]) ?><br>
                                                    <?= e($order["address"]) ?>

                                                    <?php if (!empty($order["city"])): ?>
                                                        , <?= e($order["city"]) ?>
                                                    <?php endif; ?>

                                                    <?php if (!empty($order["province"])): ?>
                                                        , <?= e($order["province"]) ?>
                                                    <?php endif; ?>

                                                    <?php if (!empty($order["postal_code"])): ?>
                                                        <?= e($order["postal_code"]) ?>
                                                    <?php endif; ?>

                                                    <?php if (!empty($order["phone"])): ?>
                                                        <br>
                                                        <small><?= e($order["phone"]) ?></small>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <span class="status <?= e($statusClass) ?>">
                                                        <?= e($statusText) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($order["shipment_id"])): ?>
                                                        <form action="../process/shipping_update.php"
                                                            method="POST" class="shipping-action-form">

                                                            <input type="hidden"
                                                                name="csrf_token"
                                                                value="<?= e($_SESSION["csrf_token"] ?? "") ?>">

                                                            <input type="hidden"
                                                                name="shipment_id"
                                                                value="<?= (int) $order["shipment_id"] ?>">

                                                            <label for="tracking_<?= (int) $order["shipment_id"] ?>">
                                                                Nomor Resi
                                                            </label>

                                                            <input
                                                                type="text"
                                                                id="tracking_<?= (int) $order["shipment_id"] ?>"
                                                                name="tracking_number"
                                                                value="<?= e($order["tracking_number"] ?? "") ?>"
                                                                placeholder="Masukkan nomor resi"
                                                                maxlength="100">

                                                            <label for="status_<?= (int) $order["shipment_id"] ?>">
                                                                Status Pengiriman
                                                            </label>

                                                            <select
                                                                id="status_<?= (int) $order["shipment_id"] ?>"
                                                                name="status"
                                                                required>

                                                                <?php
                                                                $shippingOptions = [
                                                                    "waiting" => "Menunggu Pengiriman",
                                                                    "processing" => "Sedang Diproses",
                                                                    "shipped" => "Dalam Perjalanan",
                                                                    "delivered" => "Selesai",
                                                                    "failed" => "Pengiriman Gagal"
                                                                ];
                                                                ?>

                                                                <?php foreach ($shippingOptions as $value => $label): ?>
                                                                    <option
                                                                        value="<?= e($value) ?>"
                                                                        <?= ($order["shipping_status"] ?? "waiting") === $value
                                                                            ? "selected"
                                                                            : "" ?>>
                                                                        <?= e($label) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>

                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="fa-solid fa-floppy-disk"></i>
                                                                Simpan
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="payment-code">Data pengiriman belum tersedia</span>
                                                    <?php endif; ?>
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
            const searchInput = document.getElementById("searchShipping");
            const rows = document.querySelectorAll("#shippingTableBody tr");

            if (!searchInput) return;

            searchInput.addEventListener("input", function() {
                const keyword = this.value.toLowerCase().trim();

                rows.forEach(function(row) {
                    row.style.display =
                        row.textContent.toLowerCase().includes(keyword) ? "" : "none";
                });
            });

            const params = new URLSearchParams(window.location.search);

            if (params.get("success") === "updated") {
                Swal.fire({
                    icon: "success",
                    title: "Pengiriman diperbarui",
                    text: "Nomor resi dan status pengiriman berhasil disimpan.",
                    confirmButtonColor: "#8b1e4b"
                });
            } else if (params.has("error")) {
                const messages = {
                    csrf: "Sesi tidak valid. Muat ulang halaman.",
                    invalid_data: "Data pengiriman tidak valid.",
                    tracking_required: "Isi nomor resi sebelum mengubah status menjadi Dalam Perjalanan atau Selesai.",
                    not_found: "Data pengiriman tidak ditemukan.",
                    update_failed: "Pengiriman gagal diperbarui."
                };

                Swal.fire({
                    icon: "error",
                    title: "Gagal menyimpan",
                    text: messages[params.get("error")] || "Terjadi kesalahan.",
                    confirmButtonColor: "#8b1e4b"
                });
            }

            if (params.has("success") || params.has("error")) {
                window.history.replaceState({},
                    document.title,
                    window.location.pathname
                );
            }
        });
    </script>

</body>

</html>