<?php
session_start();

require_once __DIR__ . "/../config/database.php";

date_default_timezone_set("Asia/Jakarta");

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if (!function_exists("e")) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }
}

function rupiah($number)
{
    return "Rp" . number_format((float) $number, 0, ",", ".");
}

$view = $_GET["view"] ?? "home";

$allowedViews = [
    "home",
    "products",
    "product",
    "articles",
    "article"
];

if (!in_array($view, $allowedViews, true)) {
    $view = "home";
}

$categoryId = filter_var(
    $_GET["category_id"] ?? 0,
    FILTER_VALIDATE_INT
);

$categoryId = $categoryId && $categoryId > 0 ? $categoryId : 0;

$itemId = filter_var(
    $_GET["id"] ?? 0,
    FILTER_VALIDATE_INT
);

$itemId = $itemId && $itemId > 0 ? $itemId : 0;

if ($view === "products") {
    $productUrl = "produk.php";
    if ($categoryId > 0) {
        $productUrl .= "?category_id=" . $categoryId;
    }
    header("Location: " . $productUrl);
    exit;
}

if ($view === "product") {
    header("Location: detail_produk.php?id=" . $itemId);
    exit;
}

$categoryStmt = $conn->query("
    SELECT id, name
    FROM categories
    WHERE LOWER(TRIM(status)) IN ('active', 'aktif')
    ORDER BY name ASC
");

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryIcons = [
    "fa-heart",
    "fa-wand-magic-sparkles",
    "fa-leaf",
    "fa-spa",
    "fa-droplet",
    "fa-sun",
    "fa-star",
    "fa-spray-can-sparkles"
];

$productSql = "
    SELECT
        p.id,
        p.category_id,
        p.name,
        p.description,
        p.price,
        p.weight,
        p.image,
        p.status,
        c.name AS category_name,
        (
            SELECT COALESCE(SUM(s.quantity), 0)
            FROM stock s
            WHERE s.product_id = p.id
        ) AS stock_quantity
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE LOWER(p.status) IN ('active', 'aktif')
";

$productParams = [];

if ($categoryId > 0) {
    $productSql .= " AND p.category_id = :category_id";
    $productParams["category_id"] = $categoryId;
}

$productSql .= " ORDER BY p.id DESC";

$productStmt = $conn->prepare($productSql);
$productStmt->execute($productParams);

$allProducts = $productStmt->fetchAll(PDO::FETCH_ASSOC);

// Empat produk terbaru untuk Home
$homeProducts = array_slice($allProducts, 0, 4);

// Detail produk
$selectedProduct = null;

if ($view === "product") {
    foreach ($allProducts as $item) {
        if ((int) $item["id"] === $itemId) {
            $selectedProduct = $item;
            break;
        }
    }

    if (!$selectedProduct) {
        http_response_code(404);
    }
}

// ========================================
// ARTIKEL BLOG
// ========================================

$blogStmt = $conn->query("
    SELECT id, title, slug, content, image, published_at
    FROM blog_posts
    WHERE LOWER(status) = 'published'
    ORDER BY published_at DESC, id DESC
");

$allArticles = $blogStmt->fetchAll(PDO::FETCH_ASSOC);

$homeArticles = array_slice($allArticles, 0, 3);

$selectedArticle = null;

if ($view === "article") {
    foreach ($allArticles as $item) {
        if ((int) $item["id"] === $itemId) {
            $selectedArticle = $item;
            break;
        }
    }

    if (!$selectedArticle) {
        http_response_code(404);
    }
}

// ========================================
// SESSION PELANGGAN
// ========================================

$userId = (int) (
    $_SESSION["user_id"] ??
    $_SESSION["id"] ??
    0
);

$userRole = strtolower(
    $_SESSION["user_role"] ??
        $_SESSION["role"] ??
        ""
);

$isCustomerLoggedIn = (
    $userId > 0 &&
    in_array($userRole, ["customer", "pelanggan"], true)
);

$wishlistCount = 0;
$cartCount = 0;
$wishlistIds = [];

if ($isCustomerLoggedIn) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM wishlists
            WHERE user_id = :user_id
        ");
        $stmt->execute(["user_id" => $userId]);
        $wishlistCount = (int) $stmt->fetchColumn();
        $wishlistStmt = $conn->prepare("
            SELECT product_id
            FROM wishlists
            WHERE user_id = :user_id
        ");
        $wishlistStmt->execute(["user_id" => $userId]);
        $wishlistIds = array_map("intval", $wishlistStmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $ex) {
        $wishlistCount = 0;
        $wishlistIds = [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(ci.quantity), 0)
            FROM cart_items ci
            INNER JOIN carts c ON c.id = ci.cart_id
            WHERE c.user_id = :user_id
              AND c.status = 'active'
        ");
        $stmt->execute(["user_id" => $userId]);
        $cartCount = (int) $stmt->fetchColumn();
    } catch (PDOException $ex) {
        $cartCount = 0;
    }
}

// ========================================
// JUDUL HALAMAN
// ========================================

$pageTitle = "Glow.Cosmetic";

if ($view === "products") {
    $pageTitle = "Semua Produk - Glow.Cosmetic";
} elseif ($view === "product") {
    $pageTitle = ($selectedProduct["name"] ?? "Produk Tidak Ditemukan")
        . " - Glow.Cosmetic";
} elseif ($view === "articles") {
    $pageTitle = "Artikel - Glow.Cosmetic";
} elseif ($view === "article") {
    $pageTitle = ($selectedArticle["title"] ?? "Artikel Tidak Ditemukan")
        . " - Glow.Cosmetic";
}

// Fungsi path gambar produk
function productImagePath($image)
{
    if (empty($image)) {
        return "";
    }

    // Mendukung path yang sudah diawali uploads/
    if (strpos($image, "uploads/") === 0) {
        return "../" . $image;
    }

    return "../" . ltrim($image, "/");
}

function articleImagePath($image)
{
    if (empty($image)) {
        return "";
    }

    if (strpos($image, "uploads/") === 0) {
        return "../" . $image;
    }

    return "../uploads/blog/" . ltrim($image, "/");
}

?>

<!DOCTYPE html>
<html lang="id">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/home.css?v=<?= filemtime(__DIR__ . "/../css/home.css") ?>">
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
            <a href="home.php" class="<?= $view === 'home' ? 'active' : '' ?>">Home</a>
            <a href="produk.php" class="<?= in_array($view, ['products', 'product'], true) ? 'active' : '' ?>">Produk</a>
            <a href="blog.php" class="<?= in_array($view, ['articles', 'article'], true) ? 'active' : '' ?>">Blog</a>
            <a href="faq.php">FAQ</a>
        </nav>


        <div class="desktop-actions">

            <a href="<?= $isCustomerLoggedIn ? 'wishlist.php' : '../customer/login.php' ?>"
                class="nav-icon" aria-label="Wishlist">
                <i class="fa-regular fa-heart"></i>
                <span class="wishlist-count header-count" data-header-count="wishlist" <?= $wishlistCount === 0 ? 'hidden' : '' ?>><?= $wishlistCount ?></span>
            </a>

            <a href="<?= $isCustomerLoggedIn ? 'akun.php' : '../customer/login.php' ?>"
                class="nav-icon" aria-label="Akun">
                <i class="fa-regular fa-user"></i>
            </a>

            <a href="<?= $isCustomerLoggedIn ? 'cart.php' : '../customer/login.php' ?>"
                class="nav-icon" aria-label="Keranjang">
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

            <a href="<?= $isCustomerLoggedIn ? 'cart.php' : '../customer/login.php' ?>"
                class="mobile-nav-item">
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

    <?php if ($view === "home"): ?>

        <section class="hero">
            <div class="hero-content">
                <span class="hero-label">PERAWATAN KULIT</span>

                <h1>
                    Rawat Kulitmu<br>
                    <span>Raih Versi Terbaikmu</span>
                </h1>

                <p>
                    Produk skincare pilihan untuk membantu
                    menjaga kulit tetap sehat, cerah, dan terawat.
                </p>

                <a href="produk.php" class="hero-button">
                    Belanja Sekarang
                </a>
            </div>

            <div class="hero-image">
                <img src="../img/logo-home2.png" alt="Produk Glow.Cosmetic">
            </div>
        </section>

        <section class="category-section">
            <div class="gc-page gc-category-section-content">
                <div class="gc-category-grid">

                    <?php
                    $categoryIcons = [
                        "fa-heart",
                        "fa-wand-magic-sparkles",
                        "fa-leaf",
                        "fa-spa",
                        "fa-droplet"
                    ];
                    ?>

                    <?php if ($categories): ?>
                        <?php foreach ($categories as $index => $category): ?>
                            <a
                                class="gc-category-item"
                                href="produk.php?category_id=<?= (int) $category['id'] ?>">
                                <i class="fa-solid <?= e($categoryIcons[$index % count($categoryIcons)]) ?>"></i>
                                <span><?= e($category["name"]) ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="gc-empty">Kategori belum tersedia.</div>
                    <?php endif; ?>

                </div>
            </div>
        </section>

        <section class="section">
            <div class="gc-page">

                <div class="section-header">
                    <div>
                        <span class="section-label">PILIHAN UNTUKMU</span>
                        <h2>Produk Terbaru</h2>
                    </div>

                    <a href="produk.php" class="see-all">
                        Lihat Semua →
                    </a>
                </div>

                <div class="gc-product-grid">

                    <?php if ($homeProducts): ?>
                        <?php foreach ($homeProducts as $product): ?>

                            <article class="gc-product-card">

                                <?php
                                $isWishlisted = in_array(
                                    (int) $product["id"],
                                    $wishlistIds,
                                    true
                                );
                                ?>
                               
                                <form class="gc-wishlist-form"
                                    action="../process/wishlist_action.php"
                                    method="POST">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

                                    <input type="hidden" name="product_id"
                                        value="<?= (int) $product['id'] ?>">

                                    <input type="hidden" name="action"
                                        value="<?= $isWishlisted ? 'remove' : 'add' ?>">

                                    <input type="hidden" name="return_to" value="home.php">

                                    <button type="submit"
                                        class="gc-wishlist-button <?= $isWishlisted ? 'active' : '' ?>"
                                        aria-label="<?= $isWishlisted ? 'Hapus dari wishlist' : 'Tambah ke wishlist' ?>">

                                        <i class="<?= $isWishlisted
                                                        ? 'fa-solid fa-heart'
                                                        : 'fa-regular fa-heart' ?>"></i>

                                    </button>
                                </form>
                             

                                <a
                                    href="detail_produk.php?id=<?= (int) $product['id'] ?>"
                                    class="gc-product-image">
                                    <?php if (!empty($product["image"])): ?>
                                        <img
                                            src="<?= e(productImagePath($product["image"])) ?>"
                                            alt="<?= e($product["name"]) ?>"
                                            loading="lazy">
                                    <?php else: ?>
                                        <span>Gambar belum tersedia</span>
                                    <?php endif; ?>
                                </a>

                                <div class="gc-product-info">
                                    <span class="gc-category-name">
                                        <?= e($product["category_name"] ?? "Produk") ?>
                                    </span>

                                    <h2><?= e($product["name"]) ?></h2>

                                    <span class="gc-product-price">
                                        <?= rupiah($product["price"]) ?>
                                    </span>

                                    <span class="gc-stock">
                                        <?= (int) $product["stock_quantity"] > 0
                                            ? "Sisa stok: " . (int) $product["stock_quantity"]
                                            : "Stok habis" ?>
                                    </span>

                                    <a
                                        href="detail_produk.php?id=<?= (int) $product['id'] ?>"
                                        class="gc-button">
                                        Lihat Produk
                                    </a>
                                </div>

                            </article>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="gc-empty">
                            Belum ada produk aktif. Silakan tambahkan produk melalui halaman admin.
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </section>

        <section class="promo-section">
            <div class="promo-content">
                <span>SPECIAL OFFER</span>
                <h2>Temukan Produk Favoritmu</h2>
                <p>Pilih produk kecantikan yang sesuai dengan kebutuhanmu.</p>
                <a href="produk.php">Belanja Sekarang</a>
            </div>

            <div class="promo-image">
                <img src="../img/tentang-glow2.png" alt="Glow.Cosmetic">
            </div>
        </section>

        <section class="about-section">
            <div class="about-image">
                <img src="../img/logo-home2.png" alt="Glow.Cosmetic">
            </div>

            <div class="about-content">
                <span class="section-label">TENTANG GLOW.COSMETIC</span>

                <h2>Cantik Terawat, Percaya Diri Setiap Hari</h2>

                <p>
                    Glow.Cosmetic hadir untuk menyediakan berbagai
                    produk kecantikan pilihan yang dapat membantu
                    kamu merawat diri dengan lebih mudah.
                </p>

                <p>
                    Kami percaya bahwa merawat diri bukan hanya
                    tentang penampilan, tetapi juga tentang
                    memberikan waktu untuk diri sendiri.
                </p>

                <a href="home.php#tentang" class="outline-button">
                    Tentang Kami
                </a>
            </div>
        </section>

        <section class="section article-section">
            <div class="gc-page">

                <div class="section-header">
                    <div>
                        <span class="section-label">BEAUTY TIPS</span>
                        <h2>Artikel Terbaru</h2>
                    </div>

                    <a href="home.php?view=articles" class="see-all">
                        Lihat Semua →
                    </a>
                </div>

                <div class="gc-article-grid">

                    <?php if ($homeArticles): ?>
                        <?php foreach ($homeArticles as $article): ?>

                            <article class="gc-article-card">

                                <?php if (!empty($article["image"])): ?>
                                    <img
                                        src="<?= e(articleImagePath($article["image"])) ?>"
                                        alt="<?= e($article["title"]) ?>"
                                        loading="lazy">
                                <?php else: ?>
                                    <div class="gc-empty">Gambar artikel belum tersedia.</div>
                                <?php endif; ?>

                                <div class="gc-article-content">
                                    <span class="section-label">BEAUTY TIPS</span>
                                    <h2><?= e($article["title"]) ?></h2>

                                    <p>
                                        <?= e(mb_strimwidth(
                                            strip_tags($article["content"] ?? ""),
                                            0,
                                            120,
                                            "..."
                                        )) ?>
                                    </p>

                                    <a href="home.php?view=article&id=<?= (int) $article['id'] ?>">
                                        Baca Selengkapnya →
                                    </a>
                                </div>

                            </article>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="gc-empty">
                            Belum ada artikel yang dipublikasikan.
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </section>

    <?php elseif ($view === "products"): ?>

        <main class="gc-page">

            <a href="home.php" class="gc-back">
                ← Kembali ke Home
            </a>

            <div class="gc-heading">
                <span class="section-label">GLOW.COSMETIC</span>
                <h1>Semua Produk</h1>
                <p>Temukan produk kecantikan yang sesuai dengan kebutuhanmu.</p>
            </div>

            <div class="gc-category-grid gc-product-category-grid">

                <a
                    href="produk.php"
                    class="gc-category-item <?= $categoryId === 0 ? 'active' : '' ?>">
                    <i class="fa-solid fa-border-all"></i>
                    <span>Semua Produk</span>
                </a>


                <?php foreach ($categories as $index => $category): ?>
                    <a
                        href="produk.php?category_id=<?= (int) $category['id'] ?>"
                        class="gc-category-item <?= $categoryId === (int) $category['id'] ? 'active' : '' ?>">
                        <i class="fa-solid <?= e($categoryIcons[$index % count($categoryIcons)]) ?>"></i>
                        <span><?= e($category["name"]) ?></span>
                    </a>
                <?php endforeach; ?>

            </div>

            <div class="gc-product-grid">

                <?php if ($allProducts): ?>
                    <?php foreach ($allProducts as $product): ?>

                        <article class="gc-product-card">

                            <a
                                href="detail_produk.php?id=<?= (int) $product['id'] ?>"
                                class="gc-product-image">
                                <?php if (!empty($product["image"])): ?>
                                    <img
                                        src="<?= e(productImagePath($product["image"])) ?>"
                                        alt="<?= e($product["name"]) ?>"
                                        loading="lazy">
                                <?php else: ?>
                                    <span>Gambar belum tersedia</span>
                                <?php endif; ?>
                            </a>

                            <div class="gc-product-info">
                                <span class="gc-category-name">
                                    <?= e($product["category_name"] ?? "Produk") ?>
                                </span>

                                <h2><?= e($product["name"]) ?></h2>

                                <span class="gc-product-price">
                                    <?= rupiah($product["price"]) ?>
                                </span>

                                <span class="gc-stock">
                                    <?= (int) $product["stock_quantity"] > 0
                                        ? "Sisa stok: " . (int) $product["stock_quantity"]
                                        : "Stok habis" ?>
                                </span>

                                <a
                                    href="detail_produk.php?id=<?= (int) $product['id'] ?>"
                                    class="gc-button">
                                    Lihat Produk
                                </a>
                            </div>

                        </article>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gc-empty">
                        Tidak ada produk pada kategori ini.
                    </div>
                <?php endif; ?>

            </div>

        </main>

    <?php elseif ($view === "product"): ?>

        <main class="gc-page">

            <a href="produk.php" class="gc-back">
                ← Kembali ke Produk
            </a>

            <?php if ($selectedProduct): ?>

                <div class="gc-detail">

                    <div class="gc-detail-image">
                        <?php if (!empty($selectedProduct["image"])): ?>
                            <img
                                src="<?= e(productImagePath($selectedProduct["image"])) ?>"
                                alt="<?= e($selectedProduct["name"]) ?>">
                        <?php else: ?>
                            <span>Gambar belum tersedia</span>
                        <?php endif; ?>
                    </div>

                    <div class="gc-detail-info">

                        <?php if ((int) $selectedProduct["stock_quantity"] > 0): ?>

                            <form action="../process/cart_action.php" method="POST"
                                class="js-cart-add">
                                <input type="hidden" name="csrf_token"
                                    value="<?= e($_SESSION["csrf_token"]) ?>">

                                <input type="hidden" name="product_id"
                                    value="<?= (int) $selectedProduct["id"] ?>">

                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="return_to"
                                    value="<?= e("detail_produk.php?id=" . (int) $selectedProduct["id"]) ?>">

                                <label for="quantity">Jumlah</label>
                                <input type="number" id="quantity" name="quantity"
                                    value="1" min="1"
                                    max="<?= (int) $selectedProduct["stock_quantity"] ?>"
                                    required>

                                <button type="submit" class="gc-button">
                                    <i class="fa-solid fa-cart-shopping"></i>
                                    Tambah ke Keranjang
                                </button>
                            </form>

                        <?php else: ?>

                            <button type="button" class="gc-button" disabled>
                                Stok Habis
                            </button>

                        <?php endif; ?>


                    </div>

                </div>

            <?php else: ?>

                <div class="gc-empty">
                    Produk tidak ditemukan atau sudah tidak aktif.
                </div>

            <?php endif; ?>

        </main>

    <?php elseif ($view === "articles"): ?>

        <main class="gc-page">

            <a href="home.php" class="gc-back">← Kembali ke Home</a>

            <div class="gc-heading">
                <span class="section-label">BEAUTY TIPS</span>
                <h1>Artikel Glow.Cosmetic</h1>
                <p>Tips dan informasi seputar perawatan kecantikan.</p>
            </div>

            <div class="gc-article-grid">

                <?php if ($allArticles): ?>
                    <?php foreach ($allArticles as $article): ?>

                        <article class="gc-article-card">

                            <?php if (!empty($article["image"])): ?>
                                <img
                                    src="<?= e(articleImagePath($article["image"])) ?>"
                                    alt="<?= e($article["title"]) ?>"
                                    loading="lazy">
                            <?php endif; ?>

                            <div class="gc-article-content">
                                <span class="section-label">BEAUTY TIPS</span>
                                <h2><?= e($article["title"]) ?></h2>

                                <p>
                                    <?= e(mb_strimwidth(
                                        strip_tags($article["content"] ?? ""),
                                        0,
                                        120,
                                        "..."
                                    )) ?>
                                </p>

                                <a href="home.php?view=article&id=<?= (int) $article['id'] ?>">
                                    Baca Selengkapnya →
                                </a>
                            </div>

                        </article>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gc-empty">Belum ada artikel yang dipublikasikan.</div>
                <?php endif; ?>

            </div>

        </main>

    <?php elseif ($view === "article"): ?>

        <main class="gc-page">

            <a href="home.php?view=articles" class="gc-back">
                ← Kembali ke Artikel
            </a>

            <?php if ($selectedArticle): ?>

                <article class="gc-article-detail">

                    <span class="section-label">BEAUTY TIPS</span>

                    <h1><?= e($selectedArticle["title"]) ?></h1>

                    <?php if (!empty($selectedArticle["published_at"])): ?>
                        <p>
                            <i class="fa-regular fa-calendar"></i>
                            <?= e(date(
                                "d-m-Y",
                                strtotime($selectedArticle["published_at"])
                            )) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($selectedArticle["image"])): ?>
                        <img
                            src="<?= e(articleImagePath($selectedArticle["image"])) ?>"
                            alt="<?= e($selectedArticle["title"]) ?>">
                    <?php endif; ?>

                    <div class="gc-article-body">
                        <?= nl2br(e($selectedArticle["content"] ?? "")) ?>
                    </div>

                </article>

            <?php else: ?>

                <div class="gc-empty">
                    Artikel tidak ditemukan atau belum dipublikasikan.
                </div>

            <?php endif; ?>

        </main>

    <?php endif; ?>

    <footer class="footer">

        <div class="footer-container">

            <div class="footer-brand">
                <img src="../img/logo.jpeg" alt="Glow.Cosmetic">

                <p>
                    Temukan produk kecantikan pilihan untuk merawat
                    dan meningkatkan kepercayaan dirimu.
                </p>
            </div>

            <div class="footer-column">
                <h4>Navigasi</h4>
                <a href="home.php">Home</a>
                <a href="produk.php">Produk</a>
                <a href="home.php?view=articles">Blog</a>
            </div>

            <div class="footer-column">
                <h4>Bantuan</h4>
                <a href="home.php#faq">FAQ</a>
                <a href="home.php#tentang">Tentang Kami</a>
            </div>

            <div class="footer-column">
                <h4>Ikuti Kami</h4>

                <div class="social-icons">
                    <a href="#" aria-label="Instagram">
                        <i class="fa-brands fa-instagram"></i>
                    </a>

                    <a href="#" aria-label="Facebook">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>

                    <a href="#" aria-label="TikTok">
                        <i class="fa-brands fa-tiktok"></i>
                    </a>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            © <?= date("Y") ?> Glow.Cosmetic. All Rights Reserved.
        </div>

    </footer>

    <script src="../js/cart-actions.js?v=3"></script>
    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>
</body>

</html>