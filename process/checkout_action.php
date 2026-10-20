
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

if (
    $userId < 1 ||
    !in_array($role, ["customer", "pelanggan"], true)
) {
    header("Location: ../customer/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../customer/cart.php");
    exit;
}

$csrf = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($csrf) ||
    !hash_equals($_SESSION["csrf_token"], $csrf)
) {
    exit("Sesi formulir tidak valid. Muat ulang halaman checkout.");
}

$addressId = filter_var(
    $_POST["address_id"] ?? null,
    FILTER_VALIDATE_INT
);

$shippingMethodId = filter_var(
    $_POST["shipping_method_id"] ?? null,
    FILTER_VALIDATE_INT
);

$paymentMethodId = filter_var(
    $_POST["payment_method_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (
    !$addressId || !$shippingMethodId || !$paymentMethodId ||
    $addressId < 1 || $shippingMethodId < 1 || $paymentMethodId < 1
) {
    header("Location: ../customer/checkout.php");
    exit;
}

try {
    $conn->beginTransaction();

    // Pastikan alamat benar-benar milik pelanggan yang login.
    $addressStmt = $conn->prepare("
        SELECT id
        FROM user_addresses
        WHERE id = :id AND user_id = :user_id
        LIMIT 1
    ");
    $addressStmt->execute([
        ":id" => $addressId,
        ":user_id" => $userId
    ]);

    if (!$addressStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException("Alamat pengiriman tidak valid.");
    }

    // Validasi jasa pengiriman aktif.
    $shippingStmt = $conn->prepare("
        SELECT id, base_cost
        FROM shipping_methods
        WHERE id = :id
          AND LOWER(status) IN ('active', 'aktif')
        LIMIT 1
    ");
    $shippingStmt->execute([":id" => $shippingMethodId]);
    $shipping = $shippingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$shipping) {
        throw new RuntimeException("Metode pengiriman tidak tersedia.");
    }

    // Validasi metode pembayaran aktif.
    $paymentMethodStmt = $conn->prepare("
        SELECT id, name
        FROM payment_methods
        WHERE id = :id
          AND LOWER(status) IN ('active', 'aktif')
        LIMIT 1
    ");
    $paymentMethodStmt->execute([":id" => $paymentMethodId]);
    $paymentMethod = $paymentMethodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$paymentMethod) {
        throw new RuntimeException("Metode pembayaran tidak tersedia.");
    }

    // Ambil dan kunci keranjang aktif.
    $cartStmt = $conn->prepare("
        SELECT id
        FROM carts
        WHERE user_id = :user_id
          AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $cartStmt->execute([":user_id" => $userId]);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        throw new RuntimeException("Keranjang kamu kosong.");
    }

    // Ambil produk dan stok terkini, bukan mempercayai data browser.
    $itemsStmt = $conn->prepare("
        SELECT
            ci.product_id,
            ci.quantity,
            p.name,
            p.price,
            COALESCE(s.quantity, 0) AS stock_quantity
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN stock s ON s.product_id = p.id
        WHERE ci.cart_id = :cart_id
          AND LOWER(p.status) = 'active'
        FOR UPDATE
    ");
    $itemsStmt->execute([":cart_id" => $cart["id"]]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        throw new RuntimeException("Keranjang kamu kosong.");
    }

    $subtotal = 0;

    foreach ($items as $item) {
        $quantity = (int) $item["quantity"];
        $stockQuantity = (int) $item["stock_quantity"];

        if ($quantity < 1 || $quantity > $stockQuantity) {
            throw new RuntimeException(
                "Stok produk " . $item["name"] . " tidak mencukupi."
            );
        }

        $subtotal += (float) $item["price"] * $quantity;
    }

    $shippingCost = (float) $shipping["base_cost"];
    $discount = 0;
    $total = $subtotal + $shippingCost - $discount;

    // Buat nomor pesanan unik.
    $orderNumber = "GLW-" . date("YmdHis") . "-" .
        strtoupper(bin2hex(random_bytes(3)));

    // Simpan pesanan utama.
    $orderStmt = $conn->prepare("
        INSERT INTO orders (
            user_id, address_id, order_number, order_date,
            subtotal, shipping_cost, discount, total_amount,
            payment_status, order_status
        ) VALUES (
            :user_id, :address_id, :order_number, NOW(),
            :subtotal, :shipping_cost, :discount, :total,
            'unpaid', 'pending'
        )
    ");

    $orderStmt->execute([
        ":user_id" => $userId,
        ":address_id" => $addressId,
        ":order_number" => $orderNumber,
        ":subtotal" => $subtotal,
        ":shipping_cost" => $shippingCost,
        ":discount" => $discount,
        ":total" => $total
    ]);

    $orderId = (int) $conn->lastInsertId();

    // Simpan rincian setiap produk.
    $orderItemStmt = $conn->prepare("
        INSERT INTO order_items (
            order_id, product_id, product_name,
            price, quantity, subtotal
        ) VALUES (
            :order_id, :product_id, :product_name,
            :price, :quantity, :subtotal
        )
    ");

    foreach ($items as $item) {
        $quantity = (int) $item["quantity"];
        $price = (float) $item["price"];

        $orderItemStmt->execute([
            ":order_id" => $orderId,
            ":product_id" => $item["product_id"],
            ":product_name" => $item["name"],
            ":price" => $price,
            ":quantity" => $quantity,
            ":subtotal" => $price * $quantity
        ]);
    }

    // Catat pengiriman. Status mengikuti default tabel shipments.
    $shipmentStmt = $conn->prepare("
        INSERT INTO shipments (
            order_id, shipping_method_id, shipping_cost
        ) VALUES (
            :order_id, :shipping_method_id, :shipping_cost
        )
    ");

    $shipmentStmt->execute([
        ":order_id" => $orderId,
        ":shipping_method_id" => $shippingMethodId,
        ":shipping_cost" => $shippingCost
    ]);

    // Catat pembayaran sebagai belum dibayar.
    // Status payments mengikuti default tabel.
    $paymentCode = "PAY-" . $orderNumber;

    $paymentStmt = $conn->prepare("
        INSERT INTO payments (
            order_id, payment_method_id,
            payment_code, amount
        ) VALUES (
            :order_id, :payment_method_id,
            :payment_code, :amount
        )
    ");

    $paymentStmt->execute([
        ":order_id" => $orderId,
        ":payment_method_id" => $paymentMethodId,
        ":payment_code" => $paymentCode,
        ":amount" => $total
    ]);

    // Tandai keranjang selesai diproses.
    $updateCartStmt = $conn->prepare("
        UPDATE carts
        SET status = 'converted'
        WHERE id = :cart_id AND user_id = :user_id
    ");

    $updateCartStmt->execute([
        ":cart_id" => $cart["id"],
        ":user_id" => $userId
    ]);

    $conn->commit();

    // Simpan referensi pesanan untuk halaman berikutnya.
    $_SESSION["last_order_id"] = $orderId;

    header(
        "Location: ../customer/pembayaran.php?order_id=" . $orderId
    );
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Checkout error: " . $e->getMessage());

    http_response_code(500);
    echo "Pesanan belum berhasil dibuat. Periksa konfigurasi database "
       . "atau log PHP, lalu coba kembali.";
    exit;
}
