<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!function_exists("e")) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }
}

function rupiah($number)
{
    return "Rp " . number_format((float) $number, 0, ",", ".");
}

function productImagePath($image)
{
    return empty($image) ? "" : "../" . ltrim($image, "/");
}

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$userRole = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

$isCustomerLoggedIn = $userId > 0
    && in_array($userRole, ["customer", "pelanggan"], true);

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/* =========================
   FILTER DARI URL
========================= */

$categoryId = filter_var(
    $_GET["category_id"] ?? 0,
    FILTER_VALIDATE_INT
);
$categoryId = ($categoryId && $categoryId > 0) ? $categoryId : 0;

$search = trim((string) ($_GET["search"] ?? ""));
$sort = (string) ($_GET["sort"] ?? "newest");
$priceRange = (string) ($_GET["price"] ?? "");
$page = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
$page = ($page && $page > 0) ? $page : 1;

$allowedSorts = ["newest", "low", "high"];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = "newest";
}

$priceRanges = [
    "" => [
        "label" => "Semua harga",
        "min" => null,
        "max" => null
    ],
    "under50" => [
        "label" => "Di bawah Rp 50.000",
        "min" => null,
        "max" => 50000
    ],
    "50to100" => [
        "label" => "Rp 50.000 – Rp 100.000",
        "min" => 50000,
        "max" => 100000
    ],
    "100to200" => [
        "label" => "Rp 100.000 – Rp 200.000",
        "min" => 100000,
        "max" => 200000
    ],
    "over200" => [
        "label" => "Di atas Rp 200.000",
        "min" => 200000,
        "max" => null
    ]
];

if (!array_key_exists($priceRange, $priceRanges)) {
    $priceRange = "";
}

$selectedPrice = $priceRanges[$priceRange];

/* =========================
   KATEGORI DARI DATABASE
========================= */

$categories = $conn->query("
    SELECT id, name
    FROM categories
    WHERE LOWER(TRIM(status)) IN ('active', 'aktif')
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   QUERY PRODUK DAN FILTER
========================= */

$where = [
    "LOWER(TRIM(p.status)) IN ('active', 'aktif')"
];

$params = [];

if ($categoryId > 0) {
    $where[] = "p.category_id = :category_id";
    $params[":category_id"] = $categoryId;
}

if ($search !== "") {
    $where[] = "(p.name LIKE :search_name OR p.description LIKE :search_description)";
    $params[":search_name"] = "%" . $search . "%";
    $params[":search_description"] = "%" . $search . "%";
}

if ($selectedPrice["min"] !== null) {
    $where[] = "p.price >= :min_price";
    $params[":min_price"] = $selectedPrice["min"];
}

if ($selectedPrice["max"] !== null) {
    $where[] = "p.price <= :max_price";
    $params[":max_price"] = $selectedPrice["max"];
}

$whereSql = implode(" AND ", $where);

/* Hitung produk yang cocok dengan filter */
$countStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM products p
    WHERE $whereSql
");
$countStmt->execute($params);

$totalProducts = (int) $countStmt->fetchColumn();

/* Pagination */
$perPage = 12;
$totalPages = (int) ceil($totalProducts / $perPage);
$page = max(1, min($page, max(1, $totalPages)));
$offset = ($page - 1) * $perPage;

/* Urutan hanya boleh dari daftar yang diizinkan */
$orderBy = match ($sort) {
    "low" => "p.price ASC, p.id DESC",
    "high" => "p.price DESC, p.id DESC",
    default => "p.id DESC"
};

/* Ambil produk dari database */
$sql = "
    SELECT
        p.id,
        p.category_id,
        p.name,
        p.description,
        p.price,
        p.weight,
        p.image,
        c.name AS category_name,
        (
            SELECT COALESCE(SUM(s.quantity), 0)
            FROM stock s
            WHERE s.product_id = p.id
        ) AS stock_quantity
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($sql);

foreach ($params as $key => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $value, $type);
}

$stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmt->execute();

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Wishlist dan jumlah keranjang */
$wishlistIds = [];
$wishlistCount = 0;
$cartCount = 0;

if ($isCustomerLoggedIn) {
    $wishlistStmt = $conn->prepare("
        SELECT product_id
        FROM wishlists
        WHERE user_id = :user_id
    ");
    $wishlistStmt->execute([":user_id" => $userId]);

    $wishlistIds = array_map(
        "intval",
        $wishlistStmt->fetchAll(PDO::FETCH_COLUMN)
    );

    $wishlistCount = count($wishlistIds);

    $cartStmt = $conn->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0)
        FROM carts c
        INNER JOIN cart_items ci ON ci.cart_id = c.id
        WHERE c.user_id = :user_id
          AND c.status = 'active'
    ");
    $cartStmt->execute([":user_id" => $userId]);
    $cartCount = (int) $cartStmt->fetchColumn();
}

/* URL pagination mempertahankan seluruh filter */
$queryFilters = [
    "category_id" => $categoryId ?: null,
    "search" => $search !== "" ? $search : null,
    "price" => $priceRange !== "" ? $priceRange : null,
    "sort" => $sort !== "newest" ? $sort : null
];

$queryFilters = array_filter(
    $queryFilters,
    static fn($value) => $value !== null
);

function productPageUrl($pageNumber, $filters)
{
    $filters["page"] = $pageNumber;
    return "produk.php?" . http_build_query($filters);
}

$wishlistReturnTo = productPageUrl($page, $queryFilters);
?>



<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Produk - Glow.Cosmetic</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/produk.css">
    <link rel="stylesheet" href="../css/style.css">
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

            <a href="produk.php" class="active">
                Produk
            </a>

            <a href="blog.php">
                Blog
            </a>

            <a href="faq.php">FAQ</a>

        </nav>

        <div class="desktop-actions">
            <a href="<?= $isCustomerLoggedIn ? 'wishlist.php' : 'login.php' ?>"
                class="nav-icon wishlist-nav-icon" aria-label="Wishlist">
                <i class="fa-regular fa-heart"></i>
                <span class="wishlist-count header-count" id="wishlist-count"
                    data-header-count="wishlist" <?= $wishlistCount === 0 ? 'hidden' : '' ?>><?= $wishlistCount ?></span>
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


            <a href="faq.html" class="mobile-nav-item">

                <svg viewBox="0 0 24 24">
                    <path d="M20 12L12 20L4 12L12 4L20 12Z"></path>
                    <circle cx="9" cy="9" r="1"></circle>
                    <path d="M10 14L14 10"></path>
                </svg>

                <span>FAQ</span>

            </a>


            <a href="home.php?view=articles" class="mobile-nav-item">

                <svg viewBox="0 0 24 24">
                    <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                    <path d="M8 8H16"></path>
                    <path d="M8 12H16"></path>
                    <path d="M8 16H13"></path>
                </svg>

                <span>Blog</span>

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

    <section class="page-header">

        <div class="page-header-text">

            <span class="page-label">
                GLOW.COSMETIC
            </span>

            <h1>
                Semua Produk
            </h1>

            <p>
                Temukan berbagai produk skincare dan kosmetik
                pilihan untuk merawat dan mempercantik kulitmu.
            </p>

        </div>


        <div class="page-header-image">

            <img src="../img/tentang-glow2.png" alt="Produk Glow.Cosmetic">

        </div>

    </section>


    <main class="product-page">

        <aside class="sidebar">
            <div class="filter-title">
                <div>
                    <span class="filter-eyebrow">TEMUKAN FAVORITMU</span>
                    <h2>Filter Produk</h2>
                </div>
                <i class="fa-solid fa-sliders"></i>
            </div>

            <form method="GET" action="produk.php" id="product-filter-form">

                <div class="filter-group">
                    <h3>Kategori</h3>

                    <label class="filter-option">
                        <input type="radio" name="category_id" value="0"
                            <?= $categoryId === 0 ? "checked" : "" ?>>
                        <span>Semua kategori</span>
                    </label>

                    <?php foreach ($categories as $category): ?>
                        <label class="filter-option">
                            <input type="radio" name="category_id"
                                value="<?= (int) $category["id"] ?>"
                                <?= $categoryId === (int) $category["id"] ? "checked" : "" ?>>
                            <span><?= e($category["name"]) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <h3>Rentang Harga</h3>

                    <?php foreach ($priceRanges as $key => $range): ?>
                        <label class="filter-option">
                            <input type="radio" name="price"
                                value="<?= e($key) ?>"
                                <?= $priceRange === $key ? "checked" : "" ?>>
                            <span><?= e($range["label"]) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="search" value="<?= e($search) ?>">
                <input type="hidden" name="sort" value="<?= e($sort) ?>">

                <button type="submit" class="apply-filter-btn">
                    Terapkan Filter
                </button>

                <a href="produk.php" class="reset-filter-btn">
                    <i class="fa-solid fa-rotate-left"></i> Reset Filter
                </a>
            </form>
        </aside>



        <section class="product-content">

            <form class="product-toolbar" method="GET" action="produk.php">
                <?php if ($categoryId > 0): ?>
                    <input type="hidden" name="category_id"
                        value="<?= $categoryId ?>">
                <?php endif; ?>

                <?php if ($priceRange !== ""): ?>
                    <input type="hidden" name="price"
                        value="<?= e($priceRange) ?>">
                <?php endif; ?>

                <label class="product-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" name="search"
                        value="<?= e($search) ?>"
                        placeholder="Cari nama produk atau skincare..."
                        aria-label="Cari produk">
                    <?php if ($search !== ""): ?>
                        <a href="<?= e("produk.php?" . http_build_query(array_filter([
                                        "category_id" => $categoryId ?: null,
                                        "price" => $priceRange ?: null,
                                        "sort" => $sort !== "newest" ? $sort : null
                                    ]))) ?>" aria-label="Hapus pencarian">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </label>

                <label class="sort-wrapper">
                    <span>Urutkan</span>
                    <select class="sort-product" name="sort"
                        onchange="this.form.submit()">
                        <option value="newest"
                            <?= $sort === "newest" ? "selected" : "" ?>>
                            Produk Terbaru
                        </option>
                        <option value="low"
                            <?= $sort === "low" ? "selected" : "" ?>>
                            Harga Terendah
                        </option>
                        <option value="high"
                            <?= $sort === "high" ? "selected" : "" ?>>
                            Harga Tertinggi
                        </option>
                    </select>
                </label>
            </form>

            <div class="product-grid">

                <?php if (empty($products)): ?>

                    <div class="empty-products">
                        <i class="fa-solid fa-box-open"></i>
                        <h3>Produk belum ditemukan</h3>
                        <p>Coba ubah kata kunci atau filter yang kamu pilih.</p>
                        <a href="produk.php" class="reset-filter-btn">
                            Lihat Semua Produk
                        </a>
                    </div>

                <?php else: ?>

                    <?php foreach ($products as $product): ?>
                        <?php
                        $productId = (int) $product["id"];
                        $stock = (int) $product["stock_quantity"];
                        $imagePath = productImagePath($product["image"] ?? "");
                        $isWishlisted = in_array($productId, $wishlistIds, true);
                        ?>

                        <article
                            class="product-card"
                            data-name="<?= e($product["name"]) ?>"
                            data-price="<?= (float) $product["price"] ?>">
                            <div class="product-image">

                                <a href="detail_produk.php?id=<?= $productId ?>">
                                    <?php if ($imagePath !== ""): ?>
                                        <img
                                            src="<?= e($imagePath) ?>"
                                            alt="<?= e($product["name"]) ?>"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="no-product-image" style="display:none">
                                            <i class="fa-regular fa-image"></i>
                                            <span>Gambar tidak tersedia</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-product-image">
                                            <i class="fa-regular fa-image"></i>
                                            <span>Gambar tidak tersedia</span>
                                        </div>
                                    <?php endif; ?>
                                </a>

                                <span class="product-badge">
                                    <?= $stock > 0 ? "Tersedia" : "Habis" ?>
                                </span>



                                <?php if ($isCustomerLoggedIn): ?>
                                    <form action="../process/wishlist_action.php"
                                        method="POST"
                                        class="wishlist-form">

                                        <input type="hidden" name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>">

                                        <input type="hidden" name="product_id"
                                            value="<?= $productId ?>">

                                        <input type="hidden" name="action"
                                            value="<?= $isWishlisted ? 'remove' : 'add' ?>">

                                        <input type="hidden" name="return_to"
                                            value="<?= e($wishlistReturnTo) ?>">

                                        <button type="submit"
                                            class="wishlist <?= $isWishlisted ? 'active' : '' ?>"
                                            aria-label="<?= $isWishlisted ? 'Hapus dari wishlist' : 'Tambah ke wishlist' ?>"
                                            title="<?= $isWishlisted ? 'Hapus dari wishlist' : 'Tambah ke wishlist' ?>">

                                            <i class="<?= $isWishlisted
                                                            ? 'fa-solid fa-heart'
                                                            : 'fa-regular fa-heart' ?>"></i>

                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="login.php"
                                        class="wishlist"
                                        aria-label="Login untuk menyimpan wishlist">
                                        <i class="fa-regular fa-heart"></i>
                                    </a>
                                <?php endif; ?>



                            </div>

                            <div class="product-info">

                                <span class="product-category">
                                    <?= e($product["category_name"] ?? "Skincare") ?>
                                </span>

                                <h3>
                                    <a href="detail_produk.php?id=<?= $productId ?>">
                                        <?= e($product["name"]) ?>
                                    </a>
                                </h3>

                                <p class="product-description">
                                    <?= e(mb_strimwidth(
                                        trim((string) ($product["description"] ?? "")),
                                        0,
                                        85,
                                        "..."
                                    )) ?>
                                </p>

                                <div class="product-price">
                                    <?= rupiah($product["price"]) ?>
                                </div>

                                <p class="product-stock <?= $stock > 0 ? "in-stock" : "out-stock" ?>">
                                    <i class="fa-solid <?= $stock > 0 ? "fa-circle-check" : "fa-circle-xmark" ?>"></i>
                                    <?= $stock > 0 ? "Stok: " . $stock : "Stok habis" ?>
                                </p>

                                <a href="detail_produk.php?id=<?= $productId ?>"
                                    class="detail-product-btn">
                                    Lihat Detail
                                </a>

                                <?php if ($stock > 0): ?>
                                    <?php if ($isCustomerLoggedIn): ?>
                                        <form action="../process/cart_action.php" method="POST" class="js-cart-add">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= e($_SESSION["csrf_token"]) ?>">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id"
                                                value="<?= $productId ?>">
                                            <input type="hidden" name="quantity" value="1">

                                            <button type="submit" class="add-cart">
                                                <i class="fa-solid fa-cart-plus"></i>
                                                Tambah ke Keranjang
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="login.php" class="add-cart">
                                            <i class="fa-solid fa-cart-plus"></i>
                                            Login untuk Membeli
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="button" class="add-cart" disabled>
                                        Stok Habis
                                    </button>
                                <?php endif; ?>

                            </div>
                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <div class="pagination">

                <a href="#" class="arrow">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>

                <a href="#" class="active">
                    1
                </a>

                <a href="#">
                    2
                </a>

                <a href="#">
                    3
                </a>

                <span>
                    ...
                </span>

                <a href="#">
                    6
                </a>

                <a href="#" class="arrow">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>

            </div>


        </section>

    </main>

    <script src="../js/produk.js"></script>
    <script src="../js/wishlist.js"></script>
    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>

    <script>
        function addToCart(button) {

            const productCard =
                button.closest(".product-card");

            const productImage =
                productCard.querySelector(".product-image img");

            const name =
                productCard.dataset.name;

            const price =
                Number(productCard.dataset.price);

            const image =
                productImage.src;


            let cart =
                JSON.parse(localStorage.getItem("cart")) || [];


            const existingProduct =
                cart.find(item => item.name === name);


            if (existingProduct) {

                existingProduct.quantity++;

            } else {

                cart.push({
                    name: name,
                    price: price,
                    image: image,
                    quantity: 1
                });

            }


            localStorage.setItem(
                "cart",
                JSON.stringify(cart)
            );


            /* COUNT */

            const cartCount =
                cart.reduce(
                    (total, item) => total + item.quantity,
                    0
                );

            localStorage.setItem(
                "cartCount",
                cartCount
            );


            updateCartCount();


            /* TOMBOL */

            const originalText =
                button.innerHTML;

            button.innerHTML =
                '<i class="fa-solid fa-check"></i> Ditambahkan';

            button.classList.add("added");


            setTimeout(() => {

                button.innerHTML =
                    originalText;

                button.classList.remove("added");

            }, 1000);
        }

        function updateCartCount() {
            if (typeof window.refreshCustomerHeaderCounts === "function") {
                window.refreshCustomerHeaderCounts();
            }

        }
    </script>

</body>

</html>