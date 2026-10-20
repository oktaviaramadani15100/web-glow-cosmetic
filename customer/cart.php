
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

if ($userId < 1 || !in_array($role, ["customer", "pelanggan"], true)) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function rupiah($value)
{
    return "Rp " . number_format((float) $value, 0, ",", ".");
}

// Ambil keranjang aktif milik pelanggan.
$stmt = $conn->prepare("
    SELECT id
    FROM carts
    WHERE user_id = :user_id
      AND status = 'active'
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([":user_id" => $userId]);
$cart = $stmt->fetch(PDO::FETCH_ASSOC);

$items = [];

if ($cart) {
    $stmt = $conn->prepare("
        SELECT
            ci.id AS item_id,
            ci.product_id,
            ci.quantity,
            p.name,
            p.image,
            p.price,
            (
                SELECT COALESCE(SUM(s.quantity), 0)
                FROM stock s
                WHERE s.product_id = ci.product_id
            ) AS stock_quantity
        FROM cart_items ci
        INNER JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = :cart_id
        ORDER BY ci.id DESC
    ");

    $stmt->execute([":cart_id" => $cart["id"]]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hitung total belanja.
$subtotal = 0;
$totalQuantity = 0;

foreach ($items as $item) {
    $subtotal += (float) $item["price"] * (int) $item["quantity"];
    $totalQuantity += (int) $item["quantity"];
}

$wishlistStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM wishlists
    WHERE user_id = :user_id
");
$wishlistStmt->execute([":user_id" => $userId]);
$wishlistCount = (int) $wishlistStmt->fetchColumn();

$shipping = 0;
$total = $subtotal;

// Pesan setelah update/hapus.
$successMessage = null;
$errorMessage = null;

if (isset($_GET["success"])) {
    $successMessage = "Keranjang berhasil diperbarui.";
}

if (isset($_GET["error"])) {
    $errorCode = (string) $_GET["error"];

    if ($errorCode === "stock") {
        $errorMessage = "Jumlah produk melebihi stok yang tersedia.";
    } elseif ($errorCode === "invalid_id") {
        $errorMessage = "Produk yang dipilih tidak valid.";
    } else {
        $errorMessage = "Aksi gagal. Silakan periksa kembali dan coba lagi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Keranjang Belanja - Glow.Cosmetic</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/keranjang.css?v=5">
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
                    <?= $totalQuantity === 0 ? 'hidden' : '' ?>><?= $totalQuantity ?></span>
            </a>
        </div>
    </header>

    <main class="cart-page">

        <div class="cart-breadcrumb">
            <a href="home.php">Home</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>Keranjang Belanja</span>
        </div>

        <section class="cart-heading">
            <div class="cart-heading-copy">
                <span class="cart-eyebrow">YOUR BEAUTY FAVORITES</span>
                <h1>Keranjang Belanja</h1>
                <p>Produk pilihanmu siap menemani rutinitas kecantikanmu.</p>
            </div>

            <a href="produk.php" class="cart-continue">
                <i class="fa-solid fa-arrow-left"></i>
                Lanjut Belanja
            </a>
        </section>

        <?php if ($successMessage): ?>
            <div class="cart-status">
                <i class="fa-solid fa-circle-check"></i>
                <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="cart-status is-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <div class="cart-layout">

            <section class="cart-products">

                <div class="cart-products-heading">
                    <h2>Produk Pilihanmu</h2>
                    <span><?= $totalQuantity ?> produk</span>
                </div>

                <div id="cart-container">

                    <?php if (empty($items)): ?>

                        <div class="empty-cart">
                            <div class="empty-cart-icon">
                                <i class="fa-solid fa-bag-shopping"></i>
                            </div>

                            <h3>Keranjangmu masih kosong</h3>

                            <p>
                                Sepertinya kamu belum memilih produk.
                                Yuk, temukan produk favoritmu di Glow.Cosmetic.
                            </p>

                            <a href="produk.php">
                                <i class="fa-solid fa-sparkles"></i>
                                Jelajahi Produk
                            </a>
                        </div>

                    <?php else: ?>

                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemStock = (int) $item["stock_quantity"];
                            $itemQuantity = (int) $item["quantity"];
                            $itemTotal = (float) $item["price"] * $itemQuantity;
                            ?>

                            <article class="cart-item">

                                <div class="cart-item-image">
                                    <?php if (!empty($item["image"])): ?>
                                        <img
                                            src="../<?= e($item["image"]) ?>"
                                            alt="<?= e($item["name"]) ?>">
                                    <?php else: ?>
                                        <div class="cart-no-image">
                                            <i class="fa-regular fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="cart-item-info">

                                    <div class="cart-item-top">
                                        <div class="cart-item-title">
                                            <h3><?= e($item["name"]) ?></h3>

                                            <p class="cart-item-price">
                                                <?= rupiah($item["price"]) ?>
                                            </p>

                                            <p class="cart-item-unit">
                                                Harga per produk
                                            </p>
                                        </div>
                                    </div>

                                    <div class="cart-stock <?= $itemStock < 1 ? 'is-empty' : '' ?>">
                                        <i class="fa-solid fa-box"></i>

                                        <?php if ($itemStock < 1): ?>
                                            Stok habis
                                        <?php else: ?>
                                            Sisa stok: <?= $itemStock ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="cart-item-bottom">

                                        <form
                                            action="../process/cart_action.php"
                                            method="POST"
                                            class="cart-update-form">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION["csrf_token"]) ?>">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="update">

                                            <input
                                                type="hidden"
                                                name="item_id"
                                                value="<?= (int) $item["item_id"] ?>">

                                            <input
                                                type="hidden"
                                                name="return_to"
                                                value="cart.php">

                                            <label>
                                                Jumlah
                                                <input
                                                    type="number"
                                                    name="quantity"
                                                    min="1"
                                                    max="<?= min(99, $itemStock) ?>"
                                                    value="<?= $itemQuantity ?>"
                                                    required
                                                    <?= $itemStock < 1 ? "disabled" : "" ?>>
                                            </label>

                                            <button
                                                type="submit"
                                                class="cart-update-btn"
                                                <?= $itemStock < 1 ? "disabled" : "" ?>>
                                                <i class="fa-solid fa-rotate"></i>
                                                Perbarui
                                            </button>
                                        </form>

                                        <form
                                            action="../process/cart_action.php"
                                            method="POST"
                                            class="cart-remove-form"
                                            onsubmit="return confirm('Hapus produk ini dari keranjang?')">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION["csrf_token"]) ?>">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="remove">

                                            <input
                                                type="hidden"
                                                name="item_id"
                                                value="<?= (int) $item["item_id"] ?>">

                                            <input
                                                type="hidden"
                                                name="return_to"
                                                value="cart.php">

                                            <button
                                                type="submit"
                                                class="cart-remove-btn">
                                                <i class="fa-regular fa-trash-can"></i>
                                                Hapus
                                            </button>
                                        </form>

                                    </div>

                                    <div class="cart-item-total">
                                        <span>Total produk</span>
                                        <strong><?= rupiah($itemTotal) ?></strong>
                                    </div>

                                </div>
                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>
            </section>

            <aside class="cart-summary">

                <div class="cart-summary-heading">
                    <div class="cart-summary-icon">
                        <i class="fa-solid fa-receipt"></i>
                    </div>

                    <div>
                        <h2>Ringkasan Belanja</h2>
                        <p>Rincian pesananmu</p>
                    </div>
                </div>

                <div class="summary-row">
                    <span>Jumlah produk</span>
                    <strong><?= $totalQuantity ?> item</strong>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <strong><?= rupiah($subtotal) ?></strong>
                </div>

               

                <hr class="cart-summary-divider">

                <div class="summary-total">
                    <span>Total pembayaran</span>
                    <strong><?= rupiah($total) ?></strong>
                </div>

                <?php if (!empty($items)): ?>
                    <a href="checkout.php" class="checkout-btn">
                        Lanjut ke Checkout
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <button type="button" class="checkout-btn" disabled>
                        Lanjut ke Checkout
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                <?php endif; ?>

                <div class="cart-secure-note">
                    <i class="fa-solid fa-shield-heart"></i>
                    Pastikan pesananmu sudah sesuai.
                </div>

            </aside>

        </div>

        <!-- <div class="cart-info">
            <i class="fa-solid fa-heart"></i>
            <p>
                Glow.Cosmetic menghadirkan pilihan produk kecantikan untuk
                menemani perawatanmu. Periksa kembali produk dan jumlah
                pesanan sebelum melanjutkan ke checkout.
            </p>
        </div> -->

    </main>

    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>
</body>

</html>