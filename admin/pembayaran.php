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
$stmt->execute([":id" => $adminId]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("Data admin tidak ditemukan. Silakan login kembali.");
}

$adminName = $admin["name"] ?: "Admin";
$adminRole = $admin["role"] ?: "Administrator";

date_default_timezone_set("Asia/Jakarta");

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

$tanggalHariIni = $hari[date("l")] . ", " . date("d") . " " .
    $bulan[(int) date("n")] . " " . date("Y");

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function paymentStatus($status)
{
    $statuses = [
        "pending"  => ["Belum Dibayar / Perlu Dicek", "pending"],
        "verified" => ["Terverifikasi", "success"],
        "rejected" => ["Ditolak", "cancelled"],
        "refunded" => ["Dikembalikan", "pending"]
    ];

    return $statuses[$status]
        ?? ["Tidak Diketahui", "pending"];
}

try {
    $query = $conn->prepare("
        SELECT
            p.id AS payment_id,
            p.order_id,
            p.payment_method_id,
            p.payment_code,
            p.amount AS payment_amount,
            p.proof_image,
            p.paid_at,
            p.status AS payment_status,
            p.created_at,
            o.order_number,
            o.order_date,
            o.total_amount,
            o.payment_status AS order_payment_status,
            u.name AS customer_name,
            pm.name AS payment_method
        FROM payments p
        INNER JOIN orders o ON o.id = p.order_id
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN payment_methods pm
            ON pm.id = p.payment_method_id
        ORDER BY p.created_at DESC, p.id DESC
    ");

    $query->execute();
    $payments = $query->fetchAll(PDO::FETCH_ASSOC);

    $dbError = false;
} catch (PDOException $error) {
    error_log($error->getMessage());

    $payments = [];
    $dbError = true;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Pembayaran - Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/admin-pages.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .payment-actions {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .payment-actions form {
            margin: 0;
        }

        .payment-actions button,
        .proof-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 11px;
            border: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-verify {
            color: #fff;
            background: #258653;
        }

        .btn-reject {
            color: #fff;
            background: #b83250;
        }

        .proof-link {
            color: #8b1e4b;
            background: #fce4ec;
        }

        .payment-code {
            display: block;
            margin-top: 4px;
            color: #8a7780;
            font-size: 11px;
        }

        .payment-actions button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }
    </style>
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

                <a href="pembayaran.php" class="menu-item active">
                    <i class="fa-solid fa-wallet"></i>
                    <span>Pembayaran</span>
                </a>

                <a href="pengiriman.php" class="menu-item">
                    <i class="fa-solid fa-truck"></i>
                    <span>Pengiriman</span>
                </a>

                <a href="user.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
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
                        <h1>Pembayaran</h1>
                    </div>
                </div>

                <div class="header-right">
                    <span class="date">
                        <i class="fa-regular fa-calendar"></i>
                        <?= e($tanggalHariIni) ?>
                    </span>

                    <button type="button"
                        class="notification-btn"
                        aria-label="Notifikasi">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notification-dot"></span>
                    </button>

                    <a href="profile.php"
                        class="header-avatar"
                        title="<?= e($adminName) ?>">
                        <?= e(strtoupper(substr(trim($adminName), 0, 1))) ?>
                    </a>
                </div>
            </header>

            <div class="page-content">

                <div class="management-card">

                    <div class="page-toolbar">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text"
                                id="searchPayment"
                                placeholder="Cari nomor pesanan atau customer...">
                        </div>
                    </div>

                    <?php if ($dbError): ?>

                        <p role="alert">
                            Gagal mengambil data pembayaran.
                            Periksa koneksi dan struktur database.
                        </p>

                    <?php else: ?>

                        <div class="table-wrapper">
                            <table class="admin-table">

                                <thead>
                                    <tr>
                                        <th>NO. PESANAN</th>
                                        <th>CUSTOMER</th>
                                        <th>METODE</th>
                                        <th>JUMLAH</th>
                                        <th>STATUS</th>
                                        <th>AKSI</th>
                                    </tr>
                                </thead>

                                <tbody id="paymentTableBody">

                                    <?php if (empty($payments)): ?>

                                        <tr>
                                            <td colspan="6" style="text-align:center;">
                                                Belum ada data pembayaran.
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($payments as $payment): ?>
                                            <?php
                                            [$statusText, $statusClass] =
                                                paymentStatus($payment["payment_status"]);

                                            $isVerified =
                                                $payment["payment_status"] === "verified";

                                            $isRejected =
                                                $payment["payment_status"] === "rejected";

                                            ?>

                                            <tr>
                                                <td>
                                                    <?= e($payment["order_number"]) ?>

                                                    <?php if (!empty($payment["payment_code"])): ?>
                                                        <small class="payment-code">
                                                            <?= e($payment["payment_code"]) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?= e(
                                                        $payment["customer_name"]
                                                            ?? "Customer tidak ditemukan"
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= e(
                                                        $payment["payment_method"]
                                                            ?? "Metode tidak ditemukan"
                                                    ) ?>
                                                </td>

                                                <td>
                                                    Rp <?= number_format(
                                                            (float) $payment["payment_amount"],
                                                            0,
                                                            ",",
                                                            "."
                                                        ) ?>
                                                </td>

                                                <td>
                                                    <span class="status <?= e($statusClass) ?>">
                                                        <?= e($statusText) ?>
                                                    </span>



                                                <td>
                                                    <div class="payment-actions">



                                                        <?php if (!$isVerified && !$isRejected): ?>

                                                            <form action="../process/payment_verify.php"
                                                                method="POST"
                                                                class="payment-action-form"
                                                                data-action="verify">

                                                                <input type="hidden"
                                                                    name="csrf_token"
                                                                    value="<?= e($_SESSION["csrf_token"]) ?>">

                                                                <input type="hidden"
                                                                    name="payment_id"
                                                                    value="<?= (int) $payment["payment_id"] ?>">

                                                                <input type="hidden"
                                                                    name="action"
                                                                    value="verify">

                                                                <button type="submit" class="btn-verify">
                                                                    <i class="fa-solid fa-check"></i>
                                                                    Verifikasi
                                                                </button>
                                                            </form>

                                                            <form action="../process/payment_verify.php"
                                                                method="POST"
                                                                class="payment-action-form"
                                                                data-action="reject">

                                                                <input type="hidden"
                                                                    name="csrf_token"
                                                                    value="<?= e($_SESSION["csrf_token"]) ?>">

                                                                <input type="hidden"
                                                                    name="payment_id"
                                                                    value="<?= (int) $payment["payment_id"] ?>">

                                                                <input type="hidden"
                                                                    name="action"
                                                                    value="reject">

                                                                <button type="submit"
                                                                    class="btn-reject">
                                                                    <i class="fa-solid fa-xmark"></i>
                                                                    Tolak
                                                                </button>
                                                            </form>

                                                        <?php else: ?>

                                                            <span class="payment-code">
                                                                Tidak ada aksi
                                                            </span>

                                                        <?php endif; ?>

                                                    </div>
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

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById("searchPayment");
            const rows = document.querySelectorAll("#paymentTableBody tr");

            if (searchInput) {
                searchInput.addEventListener("input", function() {
                    const keyword = this.value.toLowerCase().trim();

                    rows.forEach(function(row) {
                        row.style.display =
                            row.textContent.toLowerCase().includes(keyword) ?
                            "" :
                            "none";
                    });
                });
            }

            document.querySelectorAll(".payment-action-form").forEach(function(form) {
                form.addEventListener("submit", function(event) {
                    event.preventDefault();

                    const isVerify = form.dataset.action === "verify";

                    Swal.fire({
                        icon: isVerify ? "question" : "warning",
                        title: isVerify ? "Verifikasi pembayaran?" : "Tolak pembayaran?",
                        text: isVerify ?
                            "Pastikan uang sudah diterima dan bukti pembayaran valid." : "Pastikan bukti atau transaksi memang tidak valid.",
                        showCancelButton: true,
                        confirmButtonText: isVerify ? "Ya, verifikasi" : "Ya, tolak",
                        cancelButtonText: "Batal",
                        confirmButtonColor: isVerify ? "#258653" : "#b83250",
                        cancelButtonColor: "#777"
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            const params = new URLSearchParams(window.location.search);

            if (params.get("success") === "verified") {
                Swal.fire({
                    icon: "success",
                    title: "Pembayaran terverifikasi",
                    text: "Status pembayaran berhasil diperbarui.",
                    confirmButtonColor: "#8b1e4b"
                });
            } else if (params.get("success") === "rejected") {
                Swal.fire({
                    icon: "success",
                    title: "Pembayaran ditolak",
                    text: "Status pembayaran berhasil diperbarui.",
                    confirmButtonColor: "#8b1e4b"
                });
            } else if (params.has("error")) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal!",
                    text: "Pembayaran tidak dapat diperbarui.",
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