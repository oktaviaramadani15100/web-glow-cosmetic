<?php
require_once __DIR__ . "/../config/database.php";

$stmt = $conn->prepare("
    SELECT id, title, slug, content, image, author_id, published_at
    FROM blog_posts
    WHERE LOWER(status) = 'published'
    ORDER BY published_at DESC, id DESC
");

$stmt->execute();
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$e = static function ($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
};
?>


<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Blog - Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/blog.css">
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

            <a href="blog.php" class="active">
                Blog
            </a>

            <a href="faq.php">
                FAQ
            </a>
        </nav>

        <div class="desktop-actions">
            <a href="wishlist.php" class="nav-icon wishlist-nav-icon" aria-label="Wishlist">
                <i class="fa-regular fa-heart"></i>
                <span class="wishlist-count header-count" id="wishlist-count" data-header-count="wishlist" hidden>0</span>
            </a>

            <a href="akun.php" class="nav-icon" aria-label="Akun">
                <i class="fa-regular fa-user"></i>
            </a>

            <!-- Cart -->
            <a href="cart.php" class="nav-icon cart-icon" id="cart-icon" aria-label="Keranjang">

                <i class="fa-solid fa-cart-shopping"></i>

                <span class="cart-count header-count" id="cart-count" data-header-count="cart" hidden>0</span>

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


            <a href="blog.html" class="mobile-nav-item">

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

                <span class="cart-count mobile-cart-count header-count" id="mobile-cart-count" data-header-count="cart" hidden>
                    0
                </span>

            </a>

        </nav>

    </header>

    <main class="blog-page">

        <section class="blog-header">

            <h1>
                Artikel & Tips Kecantikan
            </h1>


            <p>
                Temukan tips dan informasi seputar
                skincare dan perawatan kecantikan.
            </p>


            <!-- SEARCH -->

            <div class="blog-search">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    id="blogSearch"
                    placeholder="Cari artikel...">

            </div>

        </section>


        <section class="blog-list" id="blogList">

            <?php if (empty($blogs)): ?>

                <div class="blog-no-articles">
                    <i class="fa-regular fa-newspaper"></i>
                    <h3>Belum Ada Artikel</h3>
                    <p>Artikel kecantikan akan segera hadir untuk kamu.</p>
                </div>

            <?php else: ?>

                <?php foreach ($blogs as $blog): ?>

                    <article class="blog-card">

                        <a href="blog-detail.php?slug=<?= urlencode($blog['slug']) ?>"
                            class="blog-image">

                            <?php if (!empty($blog['image'])): ?>
                                <img
                                    src="../uploads/blog/<?= $e(basename($blog['image'])) ?>"
                                    alt="<?= $e($blog['title']) ?>">
                            <?php else: ?>
                                <div class="blog-image-placeholder">
                                    <i class="fa-solid fa-spa"></i>
                                </div>
                            <?php endif; ?>

                        </a>

                        <div class="blog-content">

                            <h2>
                                <a href="blog-detail.php?slug=<?= urlencode($blog['slug']) ?>">
                                    <?= $e($blog['title']) ?>
                                </a>
                            </h2>

                            <p class="blog-excerpt">
                                <?= $e(mb_strimwidth(
                                    strip_tags($blog['content']),
                                    0,
                                    140,
                                    '...',
                                    'UTF-8'
                                )) ?>
                            </p>

                            <div class="blog-meta">
                                <span>
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= $e(date(
                                        'd M Y',
                                        strtotime($blog['published_at'])
                                    )) ?>
                                </span>
                            </div>

                            <a class="blog-read-more"
                                href="blog-detail.php?slug=<?= urlencode($blog['slug']) ?>">
                                Baca Selengkapnya
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>



        <!-- EMPTY SEARCH -->

        <div class="blog-empty" id="blogEmpty">

            <i class="fa-regular fa-face-sad-tear"></i>

            <h3>
                Artikel tidak ditemukan
            </h3>

            <p>
                Coba gunakan kata kunci lainnya.
            </p>

        </div>


    </main>



    <script src="../js/blog.js"></script>
    <script src="../js/wishlist.js"></script>
    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>

</body>

</html>