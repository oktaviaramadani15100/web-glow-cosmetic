<?php
session_start();
require_once __DIR__ . "/../config/database.php";

date_default_timezone_set("Asia/Jakarta");

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function rupiah($value)
{
    return "Rp " . number_format((float) $value, 0, ",", ".");
}

function productImagePath($image)
{
    if (empty($image)) {
        return "";
    }

    return "../" . ltrim($image, "/");
}

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$userRole = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");
$isCustomerLoggedIn = $userId > 0
    && in_array($userRole, ["customer", "pelanggan"], true);

$productId = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
$productId = $productId && $productId > 0 ? $productId : 0;

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.description,
        p.price,
        p.weight,
        p.image,
        c.id AS category_id,
        c.name AS category_name,
        (
            SELECT COALESCE(SUM(s.quantity), 0)
            FROM stock s
            WHERE s.product_id = p.id
        ) AS stock_quantity
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = :id
      AND LOWER(TRIM(p.status)) IN ('active', 'aktif')
    LIMIT 1
");
$stmt->execute([":id" => $productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$stock = $product ? (int) $product["stock_quantity"] : 0;
$isWishlisted = false;
$wishlistCount = 0;
$cartCount = 0;

if ($isCustomerLoggedIn) {
    $wishlistCountStmt = $conn->prepare("
        SELECT COUNT(*)
        FROM wishlists
        WHERE user_id = :user_id
    ");
    $wishlistCountStmt->execute([":user_id" => $userId]);
    $wishlistCount = (int) $wishlistCountStmt->fetchColumn();

    $cartStmt = $conn->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0)
        FROM carts c
        INNER JOIN cart_items ci ON ci.cart_id = c.id
        WHERE c.user_id = :user_id AND c.status = 'active'
    ");
    $cartStmt->execute([":user_id" => $userId]);
    $cartCount = (int) $cartStmt->fetchColumn();
}

if ($isCustomerLoggedIn && $product) {
    $wishlistStmt = $conn->prepare("
        SELECT 1
        FROM wishlists
        WHERE user_id = :user_id AND product_id = :product_id
        LIMIT 1
    ");
    $wishlistStmt->execute([
        ":user_id" => $userId,
        ":product_id" => $product["id"]
    ]);
    $isWishlisted = (bool) $wishlistStmt->fetchColumn();

}

$returnTo = "detail_produk.php?id=" . (int) ($product["id"] ?? $productId);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($product["name"] ?? "Produk Tidak Ditemukan") ?> - Glow.Cosmetic</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/home.css?v=<?= filemtime(__DIR__ . "/../css/home.css") ?>">
    <link rel="stylesheet" href="../css/customer-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <header class="navbar">
        <a href="home.php" class="logo">
            <img src="../img/logo.jpeg" alt="Glow.Cosmetic">
        </a>

        <nav class="desktop-menu">
            <a href="home.php">Home</a>
            <a href="produk.php" class="active">Produk</a>
            <a href="blog.php">Blog</a>
            <a href="faq.php">FAQ</a>
        </nav>

        <div class="desktop-actions">
            <a href="<?= $isCustomerLoggedIn ? "wishlist.php" : "login.php" ?>"
                class="nav-icon wishlist-nav-icon" aria-label="Wishlist">
                <i class="fa-regular fa-heart"></i>
                <span class="wishlist-count header-count" data-header-count="wishlist"
                    <?= $wishlistCount === 0 ? 'hidden' : '' ?>><?= $wishlistCount ?></span>
            </a>
            <a href="<?= $isCustomerLoggedIn ? "cart.php" : "login.php" ?>"
                class="nav-icon" aria-label="Keranjang">
                <i class="fa-solid fa-cart-shopping"></i>
                <span class="cart-count header-count" data-header-count="cart"
                    <?= $cartCount === 0 ? 'hidden' : '' ?>><?= $cartCount ?></span>
            </a>
        </div>
    </header>

    <main class="gc-page gc-product-detail-page">
        <a href="produk.php<?= !empty($product["category_id"])
                                ? "?category_id=" . (int) $product["category_id"]
                                : "" ?>"
            class="gc-back">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Produk
        </a>

        <?php if (isset($_GET["error"])): ?>
            <div class="gc-product-feedback" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= $_GET["error"] === "stock"
                    ? "Jumlah yang diminta melebihi stok produk yang tersedia."
                    : "Produk gagal ditambahkan. Silakan coba kembali." ?>
            </div>
        <?php endif; ?>

        <?php if ($product): ?>
            <article class="gc-detail">
                <div class="gc-detail-image">
                    <?php if (!empty($product["image"])): ?>
                        <img src="<?= e(productImagePath($product["image"])) ?>"
                            alt="<?= e($product["name"]) ?>">
                    <?php else: ?>
                        <span class="gc-product-no-image">
                            <i class="fa-regular fa-image"></i>
                            Gambar produk belum tersedia
                        </span>
                    <?php endif; ?>
                    <?php if ($stock < 1): ?>
                        <span class="gc-detail-stock-badge">Stok Habis</span>
                    <?php endif; ?>
                </div>

                <div class="gc-detail-info">
                    <span class="gc-category-name">
                        <?= e($product["category_name"] ?? "Produk") ?>
                    </span>
                    <h1><?= e($product["name"]) ?></h1>
                    <p class="gc-detail-price"><?= rupiah($product["price"]) ?></p>

                    <div class="gc-detail-stock <?= $stock > 0 ? "is-available" : "is-empty" ?>">
                        <i class="fa-solid <?= $stock > 0 ? "fa-circle-check" : "fa-circle-xmark" ?>"></i>
                        <?= $stock > 0 ? "Sisa stok: " . $stock . " produk" : "Stok sedang habis" ?>
                    </div>

                    <?php if (!empty($product["weight"])): ?>
                        <p class="gc-detail-meta">
                            <i class="fa-solid fa-box"></i>
                            Berat: <?= number_format((float) $product["weight"], 0, ",", ".") ?> gram
                        </p>
                    <?php endif; ?>

                    <div class="gc-detail-description">
                        <h2>Deskripsi Produk</h2>
                        <p><?= nl2br(e(trim($product["description"] ?? "") ?: "Belum ada deskripsi untuk produk ini.")) ?></p>
                    </div>

                    <?php if ($stock > 0): ?>
                        <form action="../process/cart_action.php" method="POST"
                            class="gc-detail-cart-form js-cart-add">
                            <input type="hidden" name="csrf_token"
                                value="<?= e($_SESSION["csrf_token"]) ?>">
                            <input type="hidden" name="product_id"
                                value="<?= (int) $product["id"] ?>">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">

                            <label for="quantity">Jumlah</label>
                            <div class="gc-detail-buy-row">
                                <input type="number" id="quantity" name="quantity"
                                    value="1" min="1" max="<?= $stock ?>" required>
                                <button type="submit" class="gc-button">
                                    <i class="fa-solid fa-cart-plus"></i>
                                    Tambah ke Keranjang
                                </button>
                            </div>
                            <small>Maksimal <?= $stock ?> produk per pesanan.</small>
                        </form>
                    <?php else: ?>
                        <button type="button" class="gc-button gc-button-disabled" disabled>
                            <i class="fa-solid fa-ban"></i>
                            Stok Habis
                        </button>
                    <?php endif; ?>

                    <form action="../process/wishlist_action.php" method="POST"
                        class="gc-detail-wishlist-form">
                        <input type="hidden" name="csrf_token"
                            value="<?= e($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="product_id"
                            value="<?= (int) $product["id"] ?>">
                        <input type="hidden" name="action"
                            value="<?= $isWishlisted ? "remove" : "add" ?>">
                        <button type="submit" class="gc-detail-wishlist">
                            <i class="<?= $isWishlisted ? "fa-solid" : "fa-regular" ?> fa-heart"></i>
                            <?= $isWishlisted ? "Hapus dari Wishlist" : "Simpan ke Wishlist" ?>
                        </button>
                    </form>
                </div>
            </article>
        <?php else: ?>
            <div class="gc-empty">
                Produk tidak ditemukan atau sudah tidak aktif.
                <a href="produk.php">Kembali ke daftar produk</a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="footer-bottom">
            © <?= date("Y") ?> Glow.Cosmetic. All Rights Reserved.
        </div>
    </footer>
    <?php if (isset($_GET["error"])): ?>
        <script>
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    icon: <?= json_encode($_GET["error"] === "stock" ? "warning" : "error") ?>,
                    title: <?= json_encode($_GET["error"] === "stock" ? "Stok tidak mencukupi" : "Gagal menambahkan produk") ?>,
                    text: <?= json_encode($_GET["error"] === "stock"
                        ? "Jumlah yang diminta melebihi stok produk yang tersedia."
                        : "Silakan coba kembali.") ?>,
                    confirmButtonColor: "#d94b78"
                });
            }
        </script>
    <?php endif; ?>
    <script src="../js/cart-actions.js?v=3"></script>
    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>
</body>

</html>
