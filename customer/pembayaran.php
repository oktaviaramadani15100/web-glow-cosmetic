<?php
session_start();

require_once __DIR__ . "/../config/database.php";

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

if ($userId < 1 || !in_array($role, ["customer", "pelanggan"], true)) {
    header("Location: login.php");
    exit;
}

$orderId = filter_var($_GET["order_id"] ?? null, FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    header("Location: cart.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
    o.id,
    o.order_number,
    o.order_date,
    o.subtotal,
    o.shipping_cost,
    o.discount,
    o.total_amount,
    o.payment_status,
    o.order_status,
    p.id AS payment_id,
    p.payment_code,
    p.proof_image,
    p.status AS transaction_status,
    pm.name AS payment_method_name,
    pm.description AS payment_method_description
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
    WHERE o.id = :order_id
      AND o.user_id = :user_id
    ORDER BY p.id DESC
    LIMIT 1
");

$stmt->execute([
    ":order_id" => $orderId,
    ":user_id" => $userId
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: akun.php");
    exit;
}

$countStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM wishlists
    WHERE user_id = :user_id
");
$countStmt->execute([":user_id" => $userId]);
$wishlistCount = (int) $countStmt->fetchColumn();

$countStmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0)
    FROM carts c
    INNER JOIN cart_items ci ON ci.cart_id = c.id
    WHERE c.user_id = :user_id
      AND c.status = 'active'
");
$countStmt->execute([":user_id" => $userId]);
$cartCount = (int) $countStmt->fetchColumn();

$e = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
};

$rupiah = static function ($amount) {
    return "Rp " . number_format((float) $amount, 0, ",", ".");
};

$methodName = $order["payment_method_name"] ?? "Belum ditentukan";
$methodLower = strtolower($methodName);

$isCod = strpos($methodLower, "cod") !== false;
$isQris = strpos($methodLower, "qris") !== false;
$isPaid = $order["payment_status"] === "paid";
$isCancelled = $order["order_status"] === "cancelled";

$paymentLabels = [
    "unpaid" => "Belum Dibayar",
    "paid" => "Lunas",
    "failed" => "Gagal",
    "refunded" => "Dikembalikan"
];

$orderLabels = [
    "pending" => "Menunggu Diproses",
    "processing" => "Sedang Diproses",
    "shipped" => "Dalam Pengiriman",
    "completed" => "Selesai",
    "cancelled" => "Dibatalkan"
];

$paymentStatus = $order["payment_status"] ?? "unpaid";
$orderStatus = $order["order_status"] ?? "pending";
$transactionStatus = $order["transaction_status"] ?? "pending";

$paymentLabel = $paymentLabels[$paymentStatus] ?? $paymentStatus;
$orderLabel = $orderLabels[$orderStatus] ?? $orderStatus;

if ($isCancelled) {
    $statusClass = "cancelled";
} elseif ($isPaid) {
    $statusClass = "success";
} elseif ($transactionStatus === "rejected") {
    $statusClass = "rejected";
} else {
    $statusClass = "waiting";
}

if ($isCancelled) {
    $statusIcon = "fa-circle-xmark";
    $statusTitle = "Pesanan Dibatalkan";
    $statusDescription = "Pesanan ini sudah dibatalkan.";
} elseif ($isPaid) {
    $statusIcon = "fa-circle-check";
    $statusTitle = "Pembayaran Berhasil";
    $statusDescription = "Pembayaran sudah tercatat lunas. Pantau perkembangan pesanan melalui akunmu.";
} elseif ($transactionStatus === "rejected") {
    $statusIcon = "fa-circle-exclamation";
    $statusTitle = "Pembayaran Ditolak";
    $statusDescription = "Pembayaran belum disetujui. Periksa informasi pesanan dan hubungi admin toko.";
} elseif ($isCod) {
    $statusIcon = "fa-truck-fast";
    $statusTitle = "Bayar Saat Barang Tiba";
    $statusDescription = "Siapkan pembayaran saat pesanan diterima. Status lunas menunggu konfirmasi pembayaran.";
} else {
    $statusIcon = "fa-wallet";
    $statusTitle = "Menunggu Pembayaran";
    $statusDescription = "Periksa total pembayaran dan ikuti instruksi resmi dari pengelola toko.";
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Pembayaran | Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">

    <style>
        :root {
            --pink: #d97996;
            --pink-dark: #b95d7b;
            --pink-light: #fff0f4;
            --pink-bg: #fff8fa;
            --text: #302832;
            --muted: #837780;
            --border: #f0e7eb;
            --green: #24865b;
            --green-bg: #eaf8f0;
            --red: #b84255;
            --red-bg: #fff0f1;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--pink-bg);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid var(--border);
        }

        .navbar .logo img {
            max-height: 48px;
            width: auto;
            object-fit: contain;
        }

        .desktop-menu a,
        .desktop-actions a {
            transition: color .2s ease;
        }

        .desktop-menu a:hover,
        .desktop-actions a:hover {
            color: var(--pink-dark);
        }

        .payment-page {
            width: min(100% - 32px, 760px);
            margin: 38px auto 60px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 22px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--pink-dark);
            text-decoration: none;
        }

        .page-heading {
            margin-bottom: 24px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            color: var(--pink-dark);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            font-size: clamp(25px, 4vw, 32px);
            letter-spacing: -.7px;
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .payment-card {
            margin-bottom: 20px;
            padding: 25px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 5px 20px rgba(85, 47, 65, .035);
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 21px;
        }

        .heading-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 43px;
            height: 43px;
            border-radius: 13px;
            background: var(--pink-light);
            color: var(--pink-dark);
            font-size: 17px;
        }

        .card-heading h2 {
            margin: 0 0 4px;
            font-size: 16px;
        }

        .card-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .status-banner {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
            padding: 22px;
            border: 1px solid #f2dbe3;
            border-radius: 17px;
            background: linear-gradient(135deg, #fff0f5, #fffafd);
        }

        .status-banner.success {
            background: var(--green-bg);
            border-color: #d4efdf;
        }

        .status-banner.cancelled,
        .status-banner.rejected {
            background: var(--red-bg);
            border-color: #f6d9dd;
        }

        .status-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 49px;
            height: 49px;
            background: #fff;
            border-radius: 15px;
            color: var(--pink-dark);
            font-size: 22px;
        }

        .success .status-icon {
            color: var(--green);
        }

        .cancelled .status-icon,
        .rejected .status-icon {
            color: var(--red);
        }

        .status-banner h2 {
            margin: 2px 0 7px;
            font-size: 19px;
        }

        .status-banner p {
            margin: 0;
            color: #766570;
            font-size: 13px;
            line-height: 1.7;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            border-radius: 20px;
            background: var(--pink-light);
            color: var(--pink-dark);
            font-size: 12px;
            font-weight: 700;
        }

        .status-pill.success {
            background: var(--green-bg);
            color: var(--green);
        }

        .status-pill.cancelled,
        .status-pill.rejected {
            background: var(--red-bg);
            color: var(--red);
        }

        .order-number-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px dashed #e9bdcc;
            border-radius: 13px;
            background: #fffafd;
        }

        .order-number-box span {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 12px;
        }

        .order-number-box strong {
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 14px 0;
            border-bottom: 1px solid #f3edf0;
            font-size: 13px;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row>span {
            color: var(--muted);
            line-height: 1.6;
        }

        .info-row>strong {
            text-align: right;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .summary-card {
            border-top: 3px solid var(--pink);
        }

        .summary-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            font-size: 13px;
        }

        .summary-row span {
            color: var(--muted);
        }

        .summary-row strong {
            text-align: right;
        }

        .total-box {
            margin-top: 13px;
            padding: 20px;
            border-radius: 14px;
            background: var(--pink-light);
        }

        .total-box>span {
            display: block;
            margin-bottom: 7px;
            color: #8d6574;
            font-size: 12px;
        }

        .total-value {
            color: var(--pink-dark);
            font-size: clamp(24px, 5vw, 30px);
            font-weight: 800;
            letter-spacing: -.5px;
            overflow-wrap: anywhere;
        }

        .payment-code {
            display: block;
            margin-top: 7px;
            color: var(--text);
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .instruction-box {
            padding: 19px;
            border-radius: 14px;
            background: #fff8fa;
            border: 1px solid #f5e5eb;
            font-size: 13px;
            line-height: 1.8;
        }

        .instruction-box p {
            margin: 0 0 12px;
        }

        .instruction-box p:last-child {
            margin-bottom: 0;
        }

        .instruction-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .instruction-note {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .instruction-note i {
            margin-top: 3px;
            color: var(--pink-dark);
        }

        .payment-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 23px;
        }

        .payment-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 46px;
            padding: 12px 18px;
            border-radius: 11px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: .2s ease;
        }

        .btn-primary-custom {
            background: var(--pink);
            color: white;
            border: 1px solid var(--pink);
        }

        .btn-primary-custom:hover {
            background: var(--pink-dark);
            border-color: var(--pink-dark);
            transform: translateY(-1px);
        }

        .btn-secondary-custom {
            background: white;
            color: var(--pink-dark);
            border: 1px solid #edcfda;
        }

        .btn-secondary-custom:hover {
            background: var(--pink-light);
        }

        .secure-note {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            margin-top: 24px;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 520px) {
            .payment-page {
                width: min(100% - 22px, 760px);
                margin-top: 25px;
            }

            .payment-card {
                padding: 18px;
                border-radius: 15px;
            }

            .status-banner {
                padding: 16px;
                gap: 12px;
            }

            .status-icon {
                width: 42px;
                height: 42px;
                font-size: 19px;
            }

            .status-banner h2 {
                font-size: 16px;
            }

            .order-number-box {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }

            .info-row {
                gap: 12px;
            }

            .payment-actions {
                flex-direction: column;
            }

            .payment-actions a {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="../css/customer-responsive.css">
</head>

<body>

    <header class="navbar">
        <a href="home.php" class="logo">
            <img src="../img/logo.jpeg" alt="Glow.Cosmetic">
        </a>

        <nav class="desktop-menu">
            <a href="home.php">Home</a>
            <a href="produk.php">Produk</a>
            <a href="blog.php">Blog</a>
            <a href="faq.php">FAQ</a>
        </nav>

        <div class="desktop-actions">
            <a href="wishlist.php" class="nav-icon wishlist-nav-icon" aria-label="Wishlist">
                <i class="fa-regular fa-heart"></i>
                <span class="wishlist-count header-count" data-header-count="wishlist"
                    <?= $wishlistCount === 0 ? 'hidden' : '' ?>><?= $wishlistCount ?></span>
            </a>
            <a href="akun.php" class="nav-icon" aria-label="Akun">
                <i class="fa-regular fa-user"></i>
            </a>
            <a href="cart.php" class="nav-icon cart-icon" aria-label="Keranjang">
                <i class="fa-solid fa-cart-shopping"></i>
                <span class="cart-count header-count" data-header-count="cart"
                    <?= $cartCount === 0 ? 'hidden' : '' ?>><?= $cartCount ?></span>
            </a>
        </div>
    </header>

    <main class="payment-page">

        <div class="breadcrumb">
            <a href="home.php">Home</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="akun.php">Pesanan Saya</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Pembayaran</span>
        </div>

        <div class="page-heading">
            <span class="eyebrow">
                <i class="fa-regular fa-heart"></i>
                Glow.Cosmetic
            </span>
            <h1>Detail Pembayaran</h1>
            <p>Hai, cantik! Periksa kembali detail pesanan dan informasi pembayaranmu.</p>
        </div>

        <section class="status-banner <?= $e($statusClass) ?>">
            <div class="status-icon">
                <i class="fa-solid <?= $e($statusIcon) ?>"></i>
            </div>

            <div>
                <h2><?= $e($statusTitle) ?></h2>
                <p><?= $e($statusDescription) ?></p>
                <div style="margin-top: 12px;">
                    <span class="status-pill <?= $e($statusClass) ?>">
                        <i class="fa-solid fa-circle"></i>
                        <?= $e($paymentLabel) ?>
                    </span>
                </div>
            </div>
        </section>

        <section class="payment-card">
            <div class="card-heading">
                <div class="heading-icon">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h2>Informasi Pesanan</h2>
                    <p>Detail transaksi belanjamu</p>
                </div>
            </div>

            <div class="order-number-box">
                <div>
                    <span>Nomor Pesanan</span>
                    <strong><?= $e($order["order_number"]) ?></strong>
                </div>
                <span class="status-pill">
                    <i class="fa-solid fa-box"></i>
                    <?= $e($orderLabel) ?>
                </span>
            </div>

            <div class="info-row">
                <span>Tanggal Pesanan</span>
                <strong>
                    <?= $e(date("d M Y, H:i", strtotime($order["order_date"]))) ?>
                </strong>
            </div>

            <div class="info-row">
                <span>Metode Pembayaran</span>
                <strong>
                    <i class="fa-solid fa-wallet" style="color: var(--pink);"></i>
                    <?= $e($methodName) ?>
                </strong>
            </div>

            <div class="info-row">
                <span>Kode Pembayaran</span>
                <strong><?= $e($order["payment_code"] ?? "-") ?></strong>
            </div>

            <div class="info-row">
                <span>Status Transaksi</span>
                <strong>
                    <?php
                    $transactionLabels = [
                        "pending" => "Menunggu Pembayaran",
                        "verified" => "Terverifikasi",
                        "rejected" => "Ditolak",
                        "refunded" => "Dana Dikembalikan"
                    ];
                    ?>
                    <?= $e($transactionLabels[$transactionStatus] ?? $transactionStatus) ?>
                </strong>
            </div>
        </section>

        <section class="payment-card summary-card">
            <div class="card-heading">
                <div class="heading-icon">
                    <i class="fa-solid fa-calculator"></i>
                </div>
                <div>
                    <h2>Ringkasan Pembayaran</h2>
                    <p>Rincian biaya pesananmu</p>
                </div>
            </div>

            <div class="summary-row">
                <span>Subtotal Produk</span>
                <strong><?= $rupiah($order["subtotal"]) ?></strong>
            </div>

            <div class="summary-row">
                <span>Ongkos Kirim</span>
                <strong><?= $rupiah($order["shipping_cost"]) ?></strong>
            </div>

            <div class="summary-row">
                <span>Diskon</span>
                <strong><?= $rupiah($order["discount"]) ?></strong>
            </div>

            <div class="total-box">
                <span>Total Pembayaran</span>
                <div class="total-value">
                    <?= $rupiah($order["total_amount"]) ?>
                </div>
                <span class="payment-code">
                    Kode: <?= $e($order["payment_code"] ?? "-") ?>
                </span>
            </div>
        </section>

        <section class="payment-card">
            <div class="card-heading">
                <div class="heading-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <div>
                    <h2>Informasi Pembayaran</h2>
                    <p>Panduan untuk pesananmu</p>
                </div>
            </div>


            <div class="instruction-box">

                <?php if ($isCancelled): ?>

                    <p><strong>Pesanan telah dibatalkan.</strong></p>
                    <p>Silakan periksa status pesanan melalui halaman akun.</p>

                <?php elseif ($isPaid): ?>

                    <p>
                        <strong>
                            <i class="fa-solid fa-circle-check"
                                style="color: var(--green);"></i>
                            Pembayaran sudah terkonfirmasi.
                        </strong>
                    </p>
                    <p>Pembayaran pesanan ini sudah tercatat lunas.</p>

                <?php elseif ($isCod): ?>

                    <p><strong>Bayar saat pesanan diterima.</strong></p>
                    <p>
                        Siapkan uang sebesar
                        <strong><?= $rupiah($order["total_amount"]) ?></strong>
                        untuk dibayarkan ketika barang diterima.
                    </p>


                <?php elseif ($isQris): ?>

                    <div class="qris-panel">

                        <div class="qris-brand">
                            <i class="fa-solid fa-qrcode"></i>
                            <div style="text-align:left;">
                                <strong>QRIS</strong>
                                <small>Quick Response Code Indonesian Standard</small>
                            </div>
                        </div>

                        <h3>Bayar dengan QRIS</h3>
                        <p>
                            Pindai kode QR menggunakan aplikasi mobile banking
                            atau e-wallet yang mendukung QRIS.
                        </p>

                        <div class="qris-placeholder">
                            <i class="fa-solid fa-qrcode"></i>
                            <div id="qris-code" style="
                                display: flex;
                                justify-content: center;
                                align-items: center;
                                background: white;
                                padding: 15px;
                                border-radius: 12px;
                                width: fit-content;
                                margin: 20px auto;
                            "></div>
                        </div>

                        <div class="qris-total">
                            <small>Total pembayaran</small>
                            <strong><?= $rupiah($order["total_amount"]) ?></strong>
                        </div>

                        <div class="qris-warning">
                            <i class="fa-solid fa-circle-info"></i>
                            Tampilan ini belum merupakan barcode pembayaran.
                            QRIS resmi perlu dibuat oleh penyedia pembayaran atau
                            menggunakan kode QR resmi milik toko.
                        </div>

                    </div>

                <?php else: ?>


                    <p><strong>Silakan selesaikan pembayaran.</strong></p>

                    <p>
                        Metode pembayaran:
                        <strong><?= $e($methodName) ?></strong>
                    </p>

                    <p>
                        Total yang harus dibayar:
                        <strong><?= $rupiah($order["total_amount"]) ?></strong>
                    </p>

                    <?php if (!empty($order["payment_method_description"])): ?>
                        <p><?= $e($order["payment_method_description"]) ?></p>
                    <?php endif; ?>

                    <p>
                        Ikuti instruksi pembayaran resmi dari pengelola toko.
                    </p>

                <?php endif; ?>

            </div>


            <div class="instruction-note">
                <i class="fa-solid fa-shield-heart"></i>
                <span>
                    Jangan melakukan pembayaran ke rekening yang belum dipastikan
                    resmi oleh pengelola Glow.Cosmetic. Status lunas hanya ditampilkan
                    berdasarkan status yang tersimpan di database.
                </span>
            </div>

            <div class="payment-actions">
                <a href="akun.php" class="btn-primary-custom">
                    <i class="fa-solid fa-box-open"></i>
                    Lihat Pesanan Saya
                </a>

                <a href="home.php" class="btn-secondary-custom">
                    <i class="fa-solid fa-house"></i>
                    Kembali Belanja
                </a>
            </div>
        </section>

        <div class="secure-note">
            <i class="fa-solid fa-heart" style="color: var(--pink);"></i>
            Terima kasih sudah berbelanja di Glow.Cosmetic
        </div>

    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <?php if ($isQris && !$isPaid && !$isCancelled): ?>
        <script>
            const qrisContainer = document.getElementById("qris-code");

            if (qrisContainer) {
                new QRCode(qrisContainer, {
                    text: <?= json_encode(
                                "Order: " . $order["order_number"] .
                                    " | Total: " . $order["total_amount"]
                            ) ?>,
                    width: 220,
                    height: 220,
                    colorDark: "#302832",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
        </script>
    <?php endif; ?>

    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>
</body>

</html>