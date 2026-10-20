<?php
session_start();

require_once __DIR__ . "/../config/database.php";

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

$isLoggedIn = $userId > 0
    && in_array($role, ["customer", "pelanggan"], true);

$addresses = [];
$profile = [];
$orders = [];
$orderItems = [];
$paymentMethods = [];
$wishlist = [];
$cartCount = 0;

$e = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
};

$rupiah = static function ($amount) {
    return "Rp " . number_format((float) $amount, 0, ",", ".");
};

if ($isLoggedIn) {
    $_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));

    // 1. Profil pelanggan
    $stmt = $conn->prepare("
        SELECT id, name, email, phone
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");
    $stmt->execute([":user_id" => $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 2. Alamat pelanggan
    $stmt = $conn->prepare("
        SELECT id, recipient_name, phone, address, city,
               province, postal_code, is_default
        FROM user_addresses
        WHERE user_id = :user_id
        ORDER BY is_default DESC, id DESC
    ");
    $stmt->execute([":user_id" => $userId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Pesanan pelanggan
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
            pm.name AS payment_method_name,
            p.status AS transaction_status
        FROM orders o
        LEFT JOIN payments p ON p.id = (
            SELECT p2.id
            FROM payments p2
            WHERE p2.order_id = o.id
            ORDER BY p2.id DESC
            LIMIT 1
        )
        LEFT JOIN payment_methods pm
            ON pm.id = p.payment_method_id
        WHERE o.user_id = :user_id
        ORDER BY o.id DESC
    ");
    $stmt->execute([":user_id" => $userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Barang dalam setiap pesanan
    if ($orders) {
        $orderIds = array_column($orders, "id");
        $placeholders = implode(",", array_fill(0, count($orderIds), "?"));

        $stmt = $conn->prepare("
            SELECT
                oi.id,
                oi.order_id,
                oi.product_id,
                oi.product_name,
                oi.price,
                oi.quantity,
                oi.subtotal,
                pr.image AS product_image
            FROM order_items oi
            LEFT JOIN products pr ON pr.id = oi.product_id
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.id ASC
        ");
        $stmt->execute($orderIds);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $orderItems[$item["order_id"]][] = $item;
        }
    }

    // 5. Metode pembayaran yang tersedia
    $stmt = $conn->query("
        SELECT id, name, description
        FROM payment_methods
        WHERE LOWER(status) IN ('active', 'aktif')
        ORDER BY name ASC
    ");
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Wishlist pelanggan
    $stmt = $conn->prepare("
        SELECT
            w.id AS wishlist_id,
            w.product_id,
            pr.name,
            pr.price,
            pr.image,
            pr.description
        FROM wishlists w
        INNER JOIN products pr ON pr.id = w.product_id
        WHERE w.user_id = :user_id
        ORDER BY w.id DESC
    ");
    $stmt->execute([":user_id" => $userId]);
    $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Jumlah produk di keranjang aktif pelanggan
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0)
        FROM carts c
        INNER JOIN cart_items ci ON ci.cart_id = c.id
        WHERE c.user_id = :user_id
          AND c.status = 'active'
    ");
    $stmt->execute([":user_id" => $userId]);
    $cartCount = (int) $stmt->fetchColumn();

}
?>



<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Akun - Glow.Cosmetic</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/akun.css">
    <link rel="stylesheet" href="../css/customer-responsive.css">

</head>


<body>


    <header class="navbar">
        <a href="home.php" class="logo">
            <img src="../img/logo.jpeg" alt="Glow.Cosmetic">
        </a>

        <nav class="desktop-menu">

            <a href="home.php">
                Home
            </a>

            <a href="produk.php">
                Produk
            </a>

            <a href="blog.php">
                Blog
            </a>

            <a href="faq.php">
                FAQ
            </a>

        </nav>

        <div class="desktop-actions">
            <a href="wishlist.php" class="nav-icon wishlist-nav-icon" aria-label="Wishlist">
                <i class="fa-regular fa-heart"></i>
                <span class="wishlist-count header-count" id="wishlist-count"
                    data-header-count="wishlist" <?= count($wishlist) === 0 ? 'hidden' : '' ?>><?= count($wishlist) ?></span>
            </a>


            <a href="akun.php" class="nav-icon" aria-label="Akun">
                <i class="fa-regular fa-user"></i>
            </a>

            <a href="cart.php" class="nav-icon cart-icon" id="cart-icon" aria-label="Keranjang">

                <i class="fa-solid fa-cart-shopping"></i>

                <span class="cart-count header-count" id="cart-count"
                    data-header-count="cart" <?= $cartCount === 0 ? 'hidden' : '' ?>><?= $cartCount ?></span>

            </a>

        </div>

        <nav class="mobile-menu">

            <a href="home.php" class="mobile-nav-item">

                <svg viewBox="0 0 24 24">
                    <path d="M3 11L12 4L21 11"></path>
                    <path d="M5 10V20H19V10"></path>
                    <path d="M9 20V14H15V20"></path>
                </svg>

                <span>Home</span>

            </a>


            <a href="produk.php" class="mobile-nav-item">

                <svg viewBox="0 0 24 24">
                    <path d="M4 7H20"></path>
                    <path d="M6 7V20H18V7"></path>
                    <path d="M9 7C9 4.8 10.3 3 12 3C13.7 3 15 4.8 15 7"></path>
                </svg>

                <span>Produk</span>

            </a>

            
            <a href="blog.php" class="mobile-nav-item">

                <svg viewBox="0 0 24 24">
                    <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                    <path d="M8 8H16"></path>
                    <path d="M8 12H16"></path>
                    <path d="M8 16H13"></path>
                </svg>

                <span>Blog</span>

            </a>

            <a href="faq.php" class="mobile-nav-item">

                <svg viewBox="0 0 24 24">
                    <path d="M20 12L12 20L4 12L12 4L20 12Z"></path>
                    <circle cx="9" cy="9" r="1"></circle>
                    <path d="M10 14L14 10"></path>
                </svg>

                <span>FAQ</span>

            </a>


            <a href="cart.php" class="mobile-nav-item" id="mobile-cart-icon">

                <svg viewBox="0 0 24 24">
                    <path d="M3 4H5L7.2 15.5C7.4 16.4 8.2 17 9.1 17H18"></path>
                    <path d="M7 8H21L19 14H8"></path>
                    <circle cx="10" cy="20" r="1.5"></circle>
                    <circle cx="18" cy="20" r="1.5"></circle>
                </svg>

                <span>Keranjang</span>

                <span class="cart-count mobile-cart-count header-count" id="mobile-cart-count"
                    data-header-count="cart" <?= $cartCount === 0 ? 'hidden' : '' ?>>
                    <?= $cartCount ?>
                </span>

            </a>

        </nav>

    </header>

    <main class="account-page">

        <section class="login-required" id="loginRequired" style="<?= $isLoggedIn ? 'display:none' : '' ?>">


            <div class="login-icon">

                <i class="fa-regular fa-user"></i>

            </div>


            <h1>
                Hai, Beauty! 💕
            </h1>


            <p>
                Login untuk melihat pesanan,
                wishlist, alamat, dan informasi akun kamu.
            </p>


            <a href="login.php" class="login-button">

                <i class="fa-solid fa-right-to-bracket"></i>

                Login

            </a>


            <div class="register-text">

                Belum punya akun?

                <a href="register.php">
                    Daftar sekarang
                </a>

            </div>


        </section>

        <section class="account-container <?= $isLoggedIn ? 'show' : '' ?>" id="accountContainer">

            <div class="account-heading">

                <h1>
                    Akun Saya
                </h1>


                <p>
                    Kelola informasi dan aktivitas
                    belanja kamu di Glow.Cosmetic.
                </p>

            </div>

            <div class="profile-card">


                <div class="profile-left">

                    <div class="profile-avatar">

                        <i class="fa-solid fa-user"></i>

                    </div>


                    <div class="profile-info">

                        <h2 id="sidebarName">
                            <?= $e($profile["name"] ?? "Pelanggan") ?>
                        </h2>

                        <p id="profileEmail">
                            <?= $e($profile["email"] ?? "-") ?>
                        </p>


                        <span>
                            Pelanggan
                        </span>

                    </div>

                </div>


                <button class="edit-profile" onclick="scrollToProfile()">

                    <i class="fa-solid fa-pen"></i>

                    Edit Profil

                </button>

            </div>

            <div class="account-menu-grid">


                <button class="account-menu-card" onclick="showAccountSection('orders')">

                    <div class="menu-card-icon pink">
                        <i class="fa-solid fa-box"></i>
                    </div>

                    <div class="menu-card-text">

                        <strong>
                            Pesanan Saya
                        </strong>

                        <span>
                            <?= count($orders) ?> pesanan untuk dilacak
                        </span>

                    </div>

                    <i class="fa-solid fa-chevron-right arrow"></i>

                </button>



                <button class="account-menu-card" onclick="showAccountSection('address')">

                    <div class="menu-card-icon purple">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                    <div class="menu-card-text">

                        <strong>
                            Alamat
                        </strong>

                        <span>
                            Kelola alamat pengiriman
                        </span>

                    </div>

                    <i class="fa-solid fa-chevron-right arrow"></i>

                </button>



                <button class="account-menu-card" onclick="showAccountSection('payment')">

                    <div class="menu-card-icon peach">
                        <i class="fa-regular fa-credit-card"></i>
                    </div>

                    <div class="menu-card-text">

                        <strong>
                            Pembayaran
                        </strong>

                        <span>
                            Metode pembayaran
                        </span>

                    </div>

                    <i class="fa-solid fa-chevron-right arrow"></i>

                </button>



                <button class="account-menu-card" onclick="showAccountSection('wishlist')">

                    <div class="menu-card-icon rose">
                        <i class="fa-regular fa-heart"></i>
                    </div>

                    <div class="menu-card-text">

                        <strong>
                            Wishlist
                        </strong>

                        <span>
                            <?= count($wishlist) ?> produk tersimpan
                        </span>

                    </div>

                    <i class="fa-solid fa-chevron-right arrow"></i>

                </button>


            </div>

            <div class="account-content">

                <section class="account-section active" id="profile">


                    <div class="section-title">

                        <div>

                            <h2>
                                Informasi Akun
                            </h2>

                            <p>
                                Pastikan informasi kamu
                                selalu terbaru.
                            </p>

                        </div>

                    </div>



                    <div class="profile-form">


                        <div class="form-group">

                            <label>
                                Nama Lengkap
                            </label>

                            <input type="text" id="accountName" value="<?= $e($profile["name"] ?? "") ?>">

                        </div>



                        <div class="form-group">

                            <label>
                                Email
                            </label>

                            <input type="email" id="accountEmail" value="<?= $e($profile["email"] ?? "") ?>">

                        </div>



                        <div class="form-group">

                            <label>
                                No. Telepon
                            </label>

                            <input type="text" id="accountPhone" value="<?= $e($profile["phone"] ?? "") ?>">

                        </div>



                        <button class="save-button" onclick="saveProfile()">

                            Simpan Perubahan

                        </button>

                    </div>

                </section>


                <section class="account-section" id="orders">

                    <div class="orders-header">
                        <div>
                            <h2>Pesanan Saya (<?= count($orders) ?>)</h2>
                            <p>Pantau pesanan dan status pembayaran kamu.</p>
                        </div>
                    </div>

                    <div class="order-tabs">
                        <button type="button" class="order-tab active"
                            data-status="all"
                            onclick="setOrderFilter('all', this)">
                            Semua (<?= count($orders) ?>)
                        </button>

                        <button type="button" class="order-tab"
                            data-status="diproses"
                            onclick="setOrderFilter('diproses', this)">
                            Diproses
                        </button>

                        <button type="button" class="order-tab"
                            data-status="dikirim"
                            onclick="setOrderFilter('dikirim', this)">
                            Dikirim
                        </button>

                        <button type="button" class="order-tab"
                            data-status="selesai"
                            onclick="setOrderFilter('selesai', this)">
                            Selesai
                        </button>

                        <button type="button" class="order-tab"
                            data-status="dibatalkan"
                            onclick="setOrderFilter('dibatalkan', this)">
                            Dibatalkan
                        </button>
                    </div>

                    <div class="orders-list" id="ordersList">

                        <?php if (!$isLoggedIn): ?>

                            <div class="orders-empty">
                                <i class="fa-regular fa-user"></i>
                                <h3>Silakan Login</h3>
                                <p>Login untuk melihat riwayat pesanan kamu.</p>
                                <a href="login.php">Login Sekarang</a>
                            </div>

                        <?php elseif (!$orders): ?>

                            <div class="orders-empty">
                                <i class="fa-solid fa-box-open"></i>
                                <h3>Belum Ada Pesanan</h3>
                                <p>Pesanan yang kamu buat akan muncul di sini.</p>
                                <a href="produk.php">Mulai Belanja</a>
                            </div>

                        <?php else: ?>

                            <?php foreach ($orders as $order): ?>

                                <?php
                                $statusRaw = strtolower(trim(
                                    $order["order_status"] ?? ""
                                ));

                                $statusFilter = match ($statusRaw) {
                                    "pending", "processing", "diproses" => "diproses",
                                    "shipped", "dikirim" => "dikirim",
                                    "completed", "selesai" => "selesai",
                                    "cancelled", "canceled", "dibatalkan" => "dibatalkan",
                                    default => $statusRaw
                                };

                                $items = $orderItems[$order["id"]] ?? [];
                                ?>

                                <article class="order-card"
                                    data-status="<?= $e($statusFilter) ?>">

                                    <div class="order-card-header">
                                        <div class="order-number">
                                            <span class="order-icon">
                                                <i class="fa-solid fa-box"></i>
                                            </span>

                                            <div>
                                                <strong>
                                                    <?= $e($order["order_number"]) ?>
                                                </strong>

                                                <small>
                                                    <?= $e(date(
                                                        "d M Y, H:i",
                                                        strtotime($order["order_date"])
                                                    )) ?>
                                                </small>
                                            </div>
                                        </div>

                                        <span class="order-status status-<?= $e($statusFilter) ?>">
                                            <?= $e(ucfirst($statusFilter)) ?>
                                        </span>
                                    </div>

                                    <div class="order-card-body">

                                        <div class="order-products">

                                            <?php foreach ($items as $item): ?>

                                                <div class="order-product">

                                                    <?php if (!empty($item["product_image"])): ?>
                                                        <img
                                                            src="../<?= $e($item["product_image"]) ?>"
                                                            alt="<?= $e($item["product_name"]) ?>">
                                                    <?php else: ?>
                                                        <div class="order-product-placeholder">
                                                            <i class="fa-solid fa-image"></i>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="order-product-info">
                                                        <strong>
                                                            <?= $e($item["product_name"]) ?>
                                                        </strong>

                                                        <span>
                                                            <?= (int) $item["quantity"] ?> ×
                                                            <?= $rupiah($item["price"]) ?>
                                                        </span>
                                                    </div>

                                                    <strong class="order-product-subtotal">
                                                        <?= $rupiah($item["subtotal"]) ?>
                                                    </strong>

                                                </div>

                                            <?php endforeach; ?>

                                        </div>

                                        <div class="order-payment-info">

                                            <div>
                                                <span>Metode pembayaran</span>
                                                <strong>
                                                    <?= $e($order["payment_method_name"] ?? "Belum ditentukan") ?>
                                                </strong>
                                            </div>

                                            <div>
                                                <span>Status pembayaran</span>
                                                <strong>
                                                    <?= $e(ucfirst($order["payment_status"] ?? "pending")) ?>
                                                </strong>
                                            </div>

                                            <div class="order-total">
                                                <span>Total pembayaran</span>
                                                <strong>
                                                    <?= $rupiah($order["total_amount"]) ?>
                                                </strong>
                                            </div>

                                        </div>

                                    </div>

                                    <div class="order-card-footer">

                                        <span class="order-item-count">
                                            <i class="fa-solid fa-bag-shopping"></i>
                                            <?= count($items) ?> jenis produk
                                        </span>

                                        <div class="order-actions">

                                            <?php if (
                                                !in_array($statusRaw, [
                                                    "cancelled",
                                                    "canceled",
                                                    "dibatalkan"
                                                ], true)
                                            ): ?>
                                                <button type="button"
                                                    class="btn-track-order"
                                                    onclick="showTracking(<?= (int) $order['id'] ?>)">
                                                    <i class="fa-solid fa-location-dot"></i>
                                                    Lacak Pesanan
                                                </button>
                                            <?php endif; ?>

                                            <?php if (
                                                strtolower($order["payment_status"] ?? "") !== "paid"
                                                && !in_array($statusRaw, [
                                                    "cancelled",
                                                    "canceled",
                                                    "dibatalkan"
                                                ], true)
                                            ): ?>
                                                <a class="btn-pay-order"
                                                    href="pembayaran.php?order_id=<?= (int) $order["id"] ?>">
                                                    <i class="fa-regular fa-credit-card"></i>
                                                    Lihat Pembayaran
                                                </a>
                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </article>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                    <div class="tracking-panel" id="trackingPanel" hidden></div>

                    <div class="orders-empty" id="ordersEmpty" style="display:none;">
                        <i class="fa-solid fa-box-open"></i>
                        <h3>Pesanan Tidak Ditemukan</h3>
                        <!-- <p>Belum ada pesanan dengan status tersebut.</p> -->
                    </div>

                </section>



                <section class="account-section" id="address">

                    <div class="section-title">
                        <div>
                            <h2>Alamat Saya</h2>
                            <p>Kelola alamat pengiriman kamu.</p>
                        </div>

                        <button class="add-address-btn" onclick="openAddressModal('add')">
                            <i class="fa-solid fa-plus"></i>
                            Tambah Alamat
                        </button>
                    </div>


                    <div class="address-list" id="addressList">

                        <?php if (!$addresses): ?>

                            <div class="empty-account">
                                <h3>Belum Ada Alamat</h3>
                                <p>Tambahkan alamat pengiriman terlebih dahulu.</p>
                            </div>

                        <?php else: ?>

                            <?php foreach ($addresses as $address): ?>

                                <div class="info-card"
                                    data-address-id="<?= (int) $address['id'] ?>"
                                    data-name="<?= htmlspecialchars($address['recipient_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-phone="<?= htmlspecialchars($address['phone'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-address="<?= htmlspecialchars($address['address'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-city="<?= htmlspecialchars($address['city'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-province="<?= htmlspecialchars($address['province'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-postal="<?= htmlspecialchars($address['postal_code'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-default="<?= (int) $address['is_default'] ?>">

                                    <div class="info-card-main">
                                        <div class="small-icon">
                                            <i class="fa-solid fa-location-dot"></i>
                                        </div>

                                        <div class="address-detail">
                                            <div class="address-name">
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $address["recipient_name"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>
                                                </strong>

                                                <?php if ($address["is_default"]): ?>
                                                    <span class="address-label">Utama</span>
                                                <?php endif; ?>
                                            </div>

                                            <p>
                                                <?= htmlspecialchars(
                                                    $address["address"] . ", " .
                                                        $address["city"] . ", " .
                                                        $address["province"] . " " .
                                                        $address["postal_code"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </p>

                                            <span>
                                                <?= htmlspecialchars(
                                                    $address["phone"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        class="edit-address-btn"
                                        onclick="openAddressModal('edit', <?= (int) $address['id'] ?>)">
                                        <i class="fa-solid fa-pen"></i>
                                        Ubah
                                    </button>
                                </div>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>


                </section>



                <section class="account-section" id="payment">

                    <div class="section-title">
                        <h2>Metode Pembayaran</h2>
                        <p>Metode pembayaran yang tersedia di Glow.Cosmetic.</p>
                    </div>

                    <?php if (!$paymentMethods): ?>

                        <div class="empty-account">
                            <h3>Belum Ada Metode Pembayaran</h3>
                            <p>Metode pembayaran belum tersedia.</p>
                        </div>

                    <?php else: ?>

                        <?php foreach ($paymentMethods as $method): ?>
                            <div class="info-card">
                                <div class="info-card-main">
                                    <div class="small-icon">
                                        <i class="fa-regular fa-credit-card"></i>
                                    </div>

                                    <div>
                                        <strong><?= $e($method["name"]) ?></strong>

                                        <p>
                                            <?= $e($method["description"] ?? "") ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php endif; ?>

                </section>


                <section class="account-section" id="wishlist">

                    <div class="section-title">

                        <h2>Wishlist (<?= count($wishlist) ?>)</h2>

                        <p>
                            Produk yang kamu simpan.
                        </p>

                    </div>



                    <div class="wishlist-list" id="wishlistList">

                        <?php if (!$wishlist): ?>

                            <div class="empty-account" id="wishlistEmpty">
                                <div class="empty-icon">
                                    <i class="fa-regular fa-heart"></i>
                                </div>
                                <h3>Wishlist Masih Kosong</h3>
                                <p>Simpan produk favorit kamu untuk dibeli nanti.</p>
                                <a href="produk.php">Lihat Produk</a>
                            </div>

                        <?php else: ?>

                            <?php foreach ($wishlist as $product): ?>
                                <div class="info-card">
                                    <div class="info-card-main">

                                        <?php if (!empty($product["image"])): ?>
                                            <img
                                                src="../<?= $e($product["image"]) ?>"
                                                alt="<?= $e($product["name"]) ?>"
                                                style="width:70px;height:70px;object-fit:cover;border-radius:10px;">
                                        <?php endif; ?>

                                        <div>
                                            <strong><?= $e($product["name"]) ?></strong>
                                            <p><?= $rupiah($product["price"]) ?></p>
                                        </div>
                                    </div>

                                    <a href="wishlist.php">Lihat Detail</a>
                                </div>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>


                </section>

                <section class="account-section" id="settings">


                    <div class="section-title">

                        <h2>
                            Pengaturan
                        </h2>

                        <p>
                            Atur preferensi akun kamu.
                        </p>

                    </div>


                    <div class="setting-list">


                        <div class="setting-item">

                            <div>

                                <strong>
                                    Notifikasi Promo
                                </strong>

                                <p>
                                    Terima informasi promo
                                    dan produk terbaru.
                                </p>

                            </div>


                            <label class="switch">

                                <input type="checkbox" checked>

                                <span class="slider"></span>

                            </label>

                        </div>



                        <div class="setting-item">

                            <div>

                                <strong>
                                    Email Marketing
                                </strong>

                                <p>
                                    Terima informasi promo
                                    melalui email.
                                </p>

                            </div>


                            <label class="switch">

                                <input type="checkbox">

                                <span class="slider"></span>

                            </label>

                        </div>

                    </div>

                </section>


            </div>

            <button class="settings-link" onclick="showAccountSection('settings')">

                <i class="fa-solid fa-gear"></i>

                Pengaturan Akun

                <i class="fa-solid fa-chevron-right"></i>

            </button>

            <button class="logout-button" id="logoutButton">

                <i class="fa-solid fa-right-from-bracket"></i>

                Keluar dari Akun

            </button>

        </section>

    </main>


    <div class="address-modal" id="addressModal">
        <div class="address-modal-content">
            <h3 id="addressModalTitle">Tambah Alamat</h3>

            <form
                id="addressForm"
                action="../process/address_action.php"
                method="POST">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <input type="hidden" name="action" value="save">
                <input type="hidden" name="address_id" id="addressId" value="">

                <label for="addressName">Nama Penerima</label>
                <input type="text" id="addressName" name="recipient_name"
                    maxlength="150" required>

                <label for="addressPhone">Nomor Telepon</label>
                <input type="tel" id="addressPhone" name="phone"
                    maxlength="30" required>

                <label for="addressDetail">Alamat Lengkap</label>
                <textarea id="addressDetail" name="address"
                    maxlength="1000" required></textarea>

                <label for="addressCity">Kota/Kabupaten</label>
                <input type="text" id="addressCity" name="city"
                    maxlength="100" required>

                <label for="addressProvince">Provinsi</label>
                <input type="text" id="addressProvince" name="province"
                    maxlength="100" required>

                <label for="addressPostal">Kode Pos</label>
                <input type="text" id="addressPostal" name="postal_code"
                    maxlength="20" required>

                <label>
                    <input type="checkbox" id="mainAddress" name="is_default" value="1">
                    Jadikan alamat utama
                </label>

                <div class="address-modal-actions">
                    <button type="submit" class="save-address-btn">
                        Simpan Alamat
                    </button>

                    <button type="button" onclick="closeAddressModal()">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>



    <script src="../js/akun.js"></script>
    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>
    <script>
        function showAccountSection(sectionId) {

            const sections =
                document.querySelectorAll(".account-section");


            sections.forEach(function(section) {

                section.classList.remove("active");

            });


            const selected =
                document.getElementById(sectionId);


            if (selected) {

                selected.classList.add("active");

                selected.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });

            }

        }


        function scrollToProfile() {

            const profile =
                document.getElementById("profile");


            document
                .querySelectorAll(".account-section")
                .forEach(function(section) {

                    section.classList.remove("active");

                });


            profile.classList.add("active");


            profile.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });

        }
    </script>


    <?php
    $messages = [
        "saved" => [
            "success",
            "Alamat Tersimpan!",
            "Alamat pengiriman berhasil disimpan."
        ],
        "deleted" => [
            "success",
            "Alamat Dihapus!",
            "Alamat berhasil dihapus."
        ],
        "default" => [
            "success",
            "Alamat Utama Diubah!",
            "Alamat utama berhasil diperbarui."
        ],
        "incomplete" => [
            "warning",
            "Data Belum Lengkap!",
            "Silakan lengkapi semua data alamat."
        ],
        "invalid" => [
            "warning",
            "Data Tidak Valid!",
            "Periksa kembali data alamat kamu."
        ],
        "not_found" => [
            "error",
            "Alamat Tidak Ditemukan!",
            "Alamat tidak ditemukan atau sudah dihapus."
        ]
    ];

    $messageKey = $_GET["message"] ?? "";
    ?>

    <?php if (isset($messages[$messageKey])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: <?= json_encode($messages[$messageKey][0]) ?>,
                    title: <?= json_encode($messages[$messageKey][1]) ?>,
                    text: <?= json_encode($messages[$messageKey][2]) ?>,
                    confirmButtonColor: "#df4772"
                });
            });
        </script>
    <?php endif; ?>



</body>

</html>