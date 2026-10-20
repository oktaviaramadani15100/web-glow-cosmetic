<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Pastikan pelanggan sudah login.
$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

if ($userId < 1 || !in_array($role, ["customer", "pelanggan"], true)) {
    header("Location: login.php");
    exit;
}

// Ambil alamat milik pelanggan.
$addressStmt = $conn->prepare("
    SELECT id, recipient_name, phone, address, city,
           province, postal_code, is_default
    FROM user_addresses
    WHERE user_id = :user_id
    ORDER BY is_default DESC, id DESC
");
$addressStmt->execute([":user_id" => $userId]);
$addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil keranjang aktif.
$cartStmt = $conn->prepare("
    SELECT id
    FROM carts
    WHERE user_id = :user_id
      AND status = 'active'
    ORDER BY id DESC
    LIMIT 1
");
$cartStmt->execute([":user_id" => $userId]);
$cart = $cartStmt->fetch(PDO::FETCH_ASSOC);

$items = [];
$subtotal = 0;
$cartCount = 0;

if ($cart) {
    $itemStmt = $conn->prepare("
        SELECT ci.product_id, ci.quantity, ci.price,
               p.name, p.image
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = :cart_id
          AND LOWER(p.status) = 'active'
        ORDER BY p.name ASC
    ");
    $itemStmt->execute([":cart_id" => $cart["id"]]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $subtotal += (float) $item["price"] * (int) $item["quantity"];
        $cartCount += (int) $item["quantity"];
    }
}

$wishlistStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM wishlists
    WHERE user_id = :user_id
");
$wishlistStmt->execute([":user_id" => $userId]);
$wishlistCount = (int) $wishlistStmt->fetchColumn();

// Ambil pilihan pengiriman yang aktif.
$shippingStmt = $conn->query("
    SELECT id, name, description, base_cost
    FROM shipping_methods
    WHERE LOWER(status) IN ('active', 'aktif')
    ORDER BY base_cost ASC
");
$shippingMethods = $shippingStmt->fetchAll(PDO::FETCH_ASSOC);


$paymentMethodsStmt = $conn->query("
    SELECT id, name, description
    FROM payment_methods
    WHERE LOWER(status) IN ('active', 'aktif')
    ORDER BY id ASC
");

$paymentMethods = $paymentMethodsStmt->fetchAll(PDO::FETCH_ASSOC);


$formatRupiah = static function ($amount) {
    return "Rp " . number_format((float) $amount, 0, ",", ".");
};

$errorMessages = [
    "empty_cart" => "Keranjang kamu masih kosong.",
    "no_address" => "Tambahkan alamat pengiriman terlebih dahulu.",
    "no_shipping" => "Belum ada jasa pengiriman yang aktif."
];

$error = $errorMessages[$_GET["error"] ?? ""] ?? "";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/checkout.css">
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

    <main class="checkout-page">

        <div class="checkout-top">
            <a href="cart.php" class="back-to-cart">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Kembali ke Keranjang</span>
            </a>

            <h1>Checkout</h1>

            <p class="checkout-breadcrumb">
                <a href="home.php">Home</a>
                <i class="fa-solid fa-chevron-right"></i>
                <a href="cart.php">Keranjang</a>
                <i class="fa-solid fa-chevron-right"></i>
                <span>Checkout</span>
            </p>

            <p class="checkout-subtitle">
                Selesaikan pesananmu dengan mengisi informasi pengiriman
                dan memilih metode pembayaran.
            </p>
        </div>

        <?php if ($error !== ""): ?>
            <p class="checkout-error">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </p>
        <?php endif; ?>

        <?php if (!$items): ?>
            <section class="checkout-card">
                <h2>Keranjang masih kosong</h2>
                <p>Silakan pilih produk terlebih dahulu sebelum checkout.</p>
                <a href="produk.php" class="next-button">Lihat Produk</a>
            </section>

        <?php elseif (!$addresses): ?>
            <section class="checkout-card">
                <h2>Alamat Pengiriman</h2>
                <p>Kamu belum memiliki alamat pengiriman.</p>
                <p>Silakan tambahkan alamat melalui halaman akun terlebih dahulu.</p>
                <a href="akun.php" class="next-button">Kelola Alamat</a>
            </section>

        <?php elseif (!$shippingMethods): ?>
            <section class="checkout-card">
                <h2>Metode Pengiriman</h2>
                <p>Belum ada jasa pengiriman aktif. Silakan aktifkan data
                    pengiriman melalui database terlebih dahulu.</p>
            </section>

        <?php else: ?>

            <form action="../process/checkout_action.php" method="POST">
                <?php
                $initialShippingCost = (float) ($shippingMethods[0]["base_cost"] ?? 0);
                ?>
                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars(
                                $_SESSION["csrf_token"] ??= bin2hex(random_bytes(32)),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>">

                <section class="checkout-card">
                    <h2>Alamat Pengiriman</h2>

                    <?php foreach ($addresses as $address): ?>
                        <label class="address-box">
                            <input type="radio"
                                name="address_id"
                                value="<?= (int) $address["id"] ?>"
                                required
                                <?= !empty($address["is_default"]) ? "checked" : "" ?>>

                            <div class="address-content">
                                <strong>
                                    <?= htmlspecialchars(
                                        $address["recipient_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </strong>

                                <p>
                                    <?= htmlspecialchars(
                                        $address["address"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </p>

                                <p>
                                    <?= htmlspecialchars(
                                        $address["city"] . ", " .
                                            $address["province"] . " " .
                                            $address["postal_code"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </p>

                                <p>
                                    <?= htmlspecialchars(
                                        $address["phone"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </p>
                            </div>
                        </label>
                    <?php endforeach; ?>

                    <a href="akun.php">Kelola alamat</a>
                </section>

                <section class="checkout-card">
                    <h2>Produk yang Dibeli</h2>


                    <?php foreach ($items as $item): ?>
                        <?php
                        $itemQuantity = (int) $item["quantity"];
                        $itemPrice = (float) $item["price"];
                        $itemSubtotal = $itemPrice * $itemQuantity;
                        ?>

                        <div class="checkout-product">
                            <div class="checkout-product-image">
                                <?php if (!empty($item["image"])): ?>
                                    <img
                                        src="../<?= htmlspecialchars(
                                                    ltrim($item["image"], "/"),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                        alt="<?= htmlspecialchars(
                                                    $item["name"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>">
                                <?php else: ?>
                                    <div class="checkout-product-no-image">
                                        <i class="fa-regular fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="checkout-product-info">
                                <strong>
                                    <?= htmlspecialchars(
                                        $item["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </strong>

                                <p><?= $formatRupiah($itemPrice) ?> / produk</p>
                                <span>Jumlah: <?= $itemQuantity ?></span>
                            </div>

                            <div class="checkout-product-subtotal">
                                <?= $formatRupiah($itemSubtotal) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="checkout-products-total">
                        <span>Subtotal produk</span>
                        <strong><?= $formatRupiah($subtotal) ?></strong>
                    </div>


                    <p>
                        <strong>Subtotal produk:</strong>
                        <?= $formatRupiah($subtotal) ?>
                    </p>
                </section>

                <section class="checkout-card">
                    <h2>Metode Pengiriman</h2>

                    <?php foreach ($shippingMethods as $index => $shipping): ?>
                        <label class="shipping-option">
                            <input type="radio"
                                name="shipping_method_id"
                                value="<?= (int) $shipping["id"] ?>"
                                required
                                <?= $index === 0 ? "checked" : "" ?>>

                            <div>
                                <strong>
                                    <?= htmlspecialchars(
                                        $shipping["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars(
                                        $shipping["description"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </small>
                            </div>

                            <span>
                                <?= $formatRupiah($shipping["base_cost"]) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>


                    <section class="checkout-card">
                        <h2>Metode Pembayaran</h2>

                        <?php if (empty($paymentMethods)): ?>
                            <p>Belum ada metode pembayaran aktif.</p>
                        <?php else: ?>
                            <?php foreach ($paymentMethods as $index => $method): ?>
                                <label class="shipping-option">
                                    <input
                                        type="radio"
                                        name="payment_method_id"
                                        value="<?= (int) $method['id'] ?>"
                                        required
                                        <?= $index === 0 ? 'checked' : '' ?>>

                                    <div>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $method['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars(
                                                $method['description'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </small>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                </section>


                <section class="checkout-card payment-summary">
                    <h2>Ringkasan Pembayaran</h2>

                    <div class="payment-row">
                        <span>Subtotal produk</span>
                        <strong id="productSubtotal"
                            data-subtotal="<?= (float) $subtotal ?>">
                            <?= $formatRupiah($subtotal) ?>
                        </strong>
                    </div>

                    <div class="payment-row">
                        <span>Ongkos kirim</span>
                        <strong id="shippingCost"
                            data-shipping="<?= $initialShippingCost ?>">
                            <?= $formatRupiah($initialShippingCost) ?>
                        </strong>
                    </div>

                    <div class="payment-divider"></div>

                    <div class="payment-total">
                        <span>Total pembayaran</span>
                        <strong id="grandTotal">
                            <?= $formatRupiah($subtotal + $initialShippingCost) ?>
                        </strong>
                    </div>

                    <button type="submit" class="next-button">
                        Buat Pesanan
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </section>


            </form>

        <?php endif; ?>

    </main>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const shippingInputs = document.querySelectorAll(
                'input[name="shipping_method_id"]'
            );

            const subtotalElement = document.getElementById("productSubtotal");
            const shippingElement = document.getElementById("shippingCost");
            const totalElement = document.getElementById("grandTotal");

            if (!subtotalElement || !shippingElement || !totalElement) {
                return;
            }

            const subtotal = Number(subtotalElement.dataset.subtotal || 0);

            function formatRupiah(amount) {
                return "Rp " + amount.toLocaleString("id-ID");
            }

            function updatePayment() {
                const selected = document.querySelector(
                    'input[name="shipping_method_id"]:checked'
                );

                const shippingCost = selected ?
                    Number(
                        selected.closest(".shipping-option")
                        .querySelector("[data-cost]")?.dataset.cost ||
                        selected.dataset.cost ||
                        0
                    ) :
                    0;

                shippingElement.textContent = formatRupiah(shippingCost);
                shippingElement.dataset.shipping = shippingCost;

                totalElement.textContent = formatRupiah(
                    subtotal + shippingCost
                );
            }

            shippingInputs.forEach(function(input) {
                const option = input.closest(".shipping-option");
                const priceElement = option?.querySelector("span");

                if (priceElement) {
                    input.dataset.cost = String(
                        Number(
                            priceElement.textContent.replace(/[^\d]/g, "")
                        )
                    );
                }

                input.addEventListener("change", updatePayment);
            });

            updatePayment();
        });
    </script>

    <script src="../js/customer-header.js"></script>
    <script src="../js/customer-navigation.js"></script>
</body>

</html>