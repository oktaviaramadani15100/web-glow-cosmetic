<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register - Glow.Cosmetic</title>

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

            <a href="produk.php">
                Produk
            </a>

            <a href="blog.php">
                Blog
            </a>

            <a href="faq.php" class="active">
                FAQ
            </a>

        </nav>
        
        <div class="desktop-actions">

            <a href="produk.php" class="nav-icon" aria-label="Search">

                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="6"></circle>
                    <path d="M16 16L21 21"></path>
                </svg>

            </a>

            <a href="wishlist.php" class="nav-icon wishlist-nav-icon" aria-label="Wishlist">
                <i class="fa-regular fa-heart"></i>
                <span class="wishlist-count header-count" data-header-count="wishlist" hidden>0</span>
            </a>

            <a href="login.php" class="nav-icon" aria-label="Account">

                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 21C4.8 16.8 7.4 15 12 15C16.6 15 19.2 16.8 20 21"></path>
                </svg>

            </a>

            <a href="cart.php" class="nav-icon" aria-label="Cart">

                <svg viewBox="0 0 24 24">
                    <path d="M3 4H5L7.2 15.5C7.4 16.4 8.2 17 9.1 17H18"></path>
                    <path d="M7 8H21L19 14H8"></path>
                    <circle cx="10" cy="20" r="1.5"></circle>
                    <circle cx="18" cy="20" r="1.5"></circle>
                </svg>
                <span class="cart-count header-count" data-header-count="cart" hidden>0</span>

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

            <a href="produk.php" class="mobile-nav-item">

                <svg viewBox="0 0 24 24">
                    <path d="M20 12L12 20L4 12L12 4L20 12Z"></path>
                    <circle cx="9" cy="9" r="1"></circle>
                    <path d="M10 14L14 10"></path>
                </svg>

                <span>Promo</span>

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

            <a href="cart.php" class="mobile-nav-item">

                <svg viewBox="0 0 24 24">
                    <path d="M3 4H5L7.2 15.5C7.4 16.4 8.2 17 9.1 17H18"></path>
                    <path d="M7 8H21L19 14H8"></path>
                    <circle cx="10" cy="20" r="1.5"></circle>
                    <circle cx="18" cy="20" r="1.5"></circle>
                </svg>

                <span>Keranjang</span>
                <span class="header-count" data-header-count="cart" hidden>0</span>

            </a>

        </nav>

    </header>

    <main class="auth-container register-container">

        <div class="auth-card register-card">

            <div class="auth-title">

                <h1>Buat Akun Baru</h1>

                <p>
                    Daftar sekarang dan nikmati pengalaman
                    belanja yang lebih nyaman.
                </p>

            </div>

            <form action="../process/register_process.php" method="POST">

                <div class="form-group">

                    <label for="name">
                        Nama Lengkap
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Masukkan nama lengkap"
                        autocomplete="name">

                </div>

                <div class="form-group">

                    <label for="email">
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Masukkan email"
                        autocomplete="email">

                </div>

                <div class="form-group">

                    <label for="phone">
                        No. HP
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="Masukkan nomor HP"
                        autocomplete="tel">

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="password-input">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Buat password"
                            autocomplete="new-password">

                        <span class="eye-icon" onclick="togglePassword('confirm-password', this)">

                            <svg viewBox="0 0 24 24">

                                <path d="M2 12C4.5 7.5 8 5 12 5C16 5 19.5 7.5 22 12C19.5 16.5 16 19 12 19C8 19 4.5 16.5 2 12Z"></path>

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="3"></circle>

                            </svg>

                        </span>

                    </div>

                </div>

                <div class="form-group">

                    <label for="confirm-password">
                        Konfirmasi Password
                    </label>

                    <div class="password-input">

                        <input
                            type="password"
                            id="confirm-password"
                            name="confirm-password"
                            placeholder="Konfirmasi password"
                            autocomplete="new-password">

                        <span class="eye-icon" onclick="togglePassword('confirm-password', this)">

                            <svg viewBox="0 0 24 24">

                                <path d="M2 12C4.5 7.5 8 5 12 5C16 5 19.5 7.5 22 12C19.5 16.5 16 19 12 19C8 19 4.5 16.5 2 12Z"></path>

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="3"></circle>

                            </svg>

                        </span>

                    </div>

                </div>

                <button
                    type="submit"
                    class="btn-primary">
                    Daftar
                </button>


            </form>

            <div class="auth-footer">

                <span>
                    Sudah punya akun?
                </span>

                <a href="login.php">
                    Login di sini
                </a>

            </div>


        </div>

    </main>

    <script>
        function togglePassword(inputId, icon) {

            const input = document.getElementById(inputId);

            if (input.type === "password") {

                input.type = "text";

                icon.classList.add("show");

            } else {

                input.type = "password";

                icon.classList.remove("show");

            }
        }
    </script>

    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>
</body>

</html>