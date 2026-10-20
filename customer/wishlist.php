
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

$stmt = $conn->prepare("
    SELECT p.id, p.name, p.price, p.image
    FROM wishlists w
    INNER JOIN products p ON p.id = w.product_id
    WHERE w.user_id = :user_id
    ORDER BY w.created_at DESC
");
$stmt->execute([":user_id" => $userId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cartStmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0)
    FROM cart_items ci
    INNER JOIN carts c ON c.id = ci.cart_id
    WHERE c.user_id = :user_id
      AND c.status = 'active'
");
$cartStmt->execute(["user_id" => $userId]);
$cartCount = (int) $cartStmt->fetchColumn();

function wishlistImagePath($image)
{
    if (empty($image)) {
        return "";
    }

    $image = ltrim($image, "/");

    if (strpos($image, "uploads/") === 0) {
        return "../" . $image;
    }

    return "../" . $image;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Wishlist Saya - Glow.Cosmetic</title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/wishlist.css">
    <link rel="stylesheet" href="../css/customer-responsive.css">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

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
            <a href="wishlist.php" class="nav-icon" aria-label="Wishlist" aria-current="page">
                <i class="fa-regular fa-heart"></i>
                <span class="wishlist-count header-count" data-header-count="wishlist"
                    <?= count($products) === 0 ? 'hidden' : '' ?>><?= count($products) ?></span>
            </a>
            <a href="akun.php" class="nav-icon" aria-label="Akun">
                <i class="fa-regular fa-user"></i>
            </a>
            <a href="cart.php" class="nav-icon" aria-label="Keranjang">
                <i class="fa-solid fa-cart-shopping"></i>
                <span class="header-count" data-header-count="cart" <?= $cartCount === 0 ? 'hidden' : '' ?>><?= $cartCount ?></span>
            </a>
        </div>

        <nav class="mobile-menu">
            <a href="home.php" class="mobile-nav-item">
                <i class="fa-solid fa-house"></i>
                <span>Home</span>
            </a>
            <a href="produk.php" class="mobile-nav-item">
                <i class="fa-solid fa-bag-shopping"></i>
                <span>Produk</span>
            </a>
            <a href="home.php?view=articles" class="mobile-nav-item">
                <i class="fa-regular fa-newspaper"></i>
                <span>Blog</span>
            </a>
            <a href="cart.php" class="mobile-nav-item">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Keranjang</span>
                <span class="header-count" data-header-count="cart" <?= $cartCount === 0 ? 'hidden' : '' ?>><?= $cartCount ?></span>
            </a>
            <a href="akun.php" class="mobile-nav-item">
                <i class="fa-regular fa-user"></i>
                <span>Akun</span>
            </a>
        </nav>
    </header>

    <main class="gc-page wishlist-page">
        <a href="produk.php" class="gc-back">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Produk
        </a>

        <section class="gc-heading wishlist-heading">
            <div>
                <span class="section-label">SIMPANAN FAVORIT</span>
                <h1>Wishlist Saya</h1>
                <p>Simpan produk favoritmu dan temukan kembali kapan saja.</p>
            </div>

            <span class="wishlist-total">
                <?= count($products) ?> Produk
            </span>
        </section>

        <?php if (isset($_GET["success"])): ?>
            <div class="wishlist-alert" role="status">
                <i class="fa-solid fa-circle-check"></i>
                Wishlist berhasil diperbarui.
            </div>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <section class="gc-empty wishlist-empty">
                <i class="fa-regular fa-heart"></i>
                <h2>Wishlist kamu masih kosong</h2>
                <p>
                    Yuk, temukan produk perawatan favoritmu dan simpan
                    di sini supaya mudah ditemukan lagi.
                </p>
                <a href="produk.php" class="gc-button">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Jelajahi Produk
                </a>
            </section>
        <?php else: ?>
            <section class="gc-product-grid wishlist-grid">
                <?php foreach ($products as $product): ?>
                    <article class="gc-product-card wishlist-product-card">
                        <a class="gc-product-image wishlist-product-image"
                            href="detail_produk.php?id=<?= (int) $product["id"] ?>">
                            <?php if (!empty($product["image"])): ?>
                                <img
                                    src="<?= e(wishlistImagePath($product["image"])) ?>"
                                    alt="<?= e($product["name"]) ?>"
                                    loading="lazy">
                            <?php else: ?>
                                <span class="wishlist-image-placeholder">
                                    <i class="fa-solid fa-spa"></i>
                                </span>
                            <?php endif; ?>
                        </a>
                        <form
                                class="gc-wishlist-form wishlist-remove-form"
                                action="../process/wishlist_action.php"
                                method="POST"
                                onsubmit="return confirm('Hapus produk ini dari wishlist?');">
                                <input type="hidden" name="csrf_token"
                                    value="<?= e($_SESSION["csrf_token"]) ?>">

                                <input type="hidden" name="product_id"
                                    value="<?= (int) $product["id"] ?>">

                                <input type="hidden" name="action" value="remove">

                                <button
                                    class="gc-wishlist-button"
                                    type="submit"
                                    aria-label="Hapus <?= e($product["name"]) ?> dari wishlist"
                                    title="Hapus dari wishlist">
                                    <i class="fa-solid fa-heart"></i>
                                </button>
                        </form>
                        <div class="gc-product-info">
                            <h2>
                                <?= e($product["name"]) ?>
                            </h2>
                            <span class="gc-product-price">
                                Rp <?= number_format((float) $product["price"], 0, ",", ".") ?>
                            </span>
                            <form
                                action="../process/cart_action.php"
                                method="POST"
                                class="js-cart-add wishlist-cart-form">
                                <input type="hidden" name="csrf_token"
                                    value="<?= e($_SESSION["csrf_token"]) ?>">
                                <input type="hidden" name="product_id"
                                    value="<?= (int) $product["id"] ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="quantity" value="1">
                                <button class="gc-button" type="submit">
                                    <i class="fa-solid fa-bag-shopping"></i>
                                    Tambah ke Keranjang
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer class="footer-bottom wishlist-footer">
        &copy; <?= date("Y") ?> Glow.Cosmetic — Your beauty, your confidence.
    </footer>

    <script src="../js/cart-actions.js?v=3"></script>
    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>

</body>

</html>
