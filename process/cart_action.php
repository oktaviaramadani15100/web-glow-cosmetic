<?php
session_start();
require_once __DIR__ . "/../config/database.php";

$expectsJson = strpos(
    strtolower((string) ($_SERVER["HTTP_ACCEPT"] ?? "")),
    "application/json"
) !== false;

function cartActionResponse(array $payload, int $statusCode = 200)
{
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($payload);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../customer/cart.php");
    exit;
}

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

if ($userId < 1 || !in_array($role, ["customer", "pelanggan"], true)) {
    if ($expectsJson) {
        cartActionResponse(["success" => false, "message" => "Silakan masuk untuk menggunakan keranjang."], 401);
    }

    header("Location: ../customer/login.php");
    exit;
}

$token = (string) ($_POST["csrf_token"] ?? "");

if (
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $token)
) {
    if ($expectsJson) {
        cartActionResponse(["success" => false, "message" => "Permintaan tidak valid. Muat ulang halaman lalu coba lagi."], 403);
    }

    exit("Permintaan tidak valid. Silakan kembali dan coba lagi.");
}

$action = $_POST["action"] ?? "";
$productId = filter_var($_POST["product_id"] ?? null, FILTER_VALIDATE_INT);
$returnTarget = "../customer/cart.php";
$returnTo = (string) ($_POST["return_to"] ?? "");

if (
    preg_match('/^produk\.php(?:\?category_id=[1-9][0-9]*)?$/D', $returnTo) ||
    preg_match('/^detail_produk\.php\?id=[1-9][0-9]*$/D', $returnTo) ||
    $returnTo === "cart.php"
) {
    $returnTarget = "../customer/" . $returnTo;
}

try {
    $conn->beginTransaction();

    // Cari atau buat keranjang aktif milik pelanggan.
    $stmt = $conn->prepare("
        SELECT id FROM carts
        WHERE user_id = :user_id AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([":user_id" => $userId]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        $stmt = $conn->prepare("
            INSERT INTO carts (user_id, status)
            VALUES (:user_id, 'active')
        ");
        $stmt->execute([":user_id" => $userId]);
        $cartId = (int) $conn->lastInsertId();
    } else {
        $cartId = (int) $cart["id"];
    }

    if ($action === "add") {
        if (!$productId || $productId < 1) {
            throw new RuntimeException("Produk tidak valid.");
        }

        $quantity = filter_var(
            $_POST["quantity"] ?? 1,
            FILTER_VALIDATE_INT
        );
        $quantity = $quantity === false ? 1 : $quantity;

        if ($quantity < 1 || $quantity > 99) {
            throw new RuntimeException("Jumlah produk tidak valid.");
        }

        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.price,
                (
                    SELECT COALESCE(SUM(s.quantity), 0)
                    FROM stock s
                    WHERE s.product_id = p.id
                ) AS stock_quantity
            FROM products p
            WHERE p.id = :id
              AND LOWER(p.status) IN ('active', 'aktif')
            LIMIT 1
        ");
        $stmt->execute([":id" => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException("Produk tidak tersedia.");
        }

        $stmt = $conn->prepare("
            SELECT id, quantity FROM cart_items
            WHERE cart_id = :cart_id AND product_id = :product_id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            ":cart_id" => $cartId,
            ":product_id" => $productId
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        $newQuantity = $quantity + (int) ($item["quantity"] ?? 0);

        if ($newQuantity > (int) $product["stock_quantity"]) {
            throw new RuntimeException("Jumlah melebihi stok yang tersedia.");
        }

        if ($item) {
            $stmt = $conn->prepare("
                UPDATE cart_items
                SET quantity = :quantity, price = :price
                WHERE id = :id AND cart_id = :cart_id
            ");
            $stmt->execute([
                ":quantity" => $newQuantity,
                ":price" => $product["price"],
                ":id" => $item["id"],
                ":cart_id" => $cartId
            ]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO cart_items (cart_id, product_id, quantity, price)
                VALUES (:cart_id, :product_id, :quantity, :price)
            ");
            $stmt->execute([
                ":cart_id" => $cartId,
                ":product_id" => $productId,
                ":quantity" => $quantity,
                ":price" => $product["price"]
            ]);
        }
    } elseif ($action === "update") {
        $itemId = filter_var($_POST["item_id"] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($_POST["quantity"] ?? null, FILTER_VALIDATE_INT);

        if (!$itemId || $itemId < 1 || $quantity === false || $quantity < 1 || $quantity > 99) {
            throw new RuntimeException("Jumlah produk tidak valid.");
        }

        $stmt = $conn->prepare("
            SELECT
                ci.product_id,
                (
                    SELECT COALESCE(SUM(s.quantity), 0)
                    FROM stock s
                    WHERE s.product_id = ci.product_id
                ) AS stock_quantity
            FROM cart_items ci
            WHERE ci.id = :item_id AND ci.cart_id = :cart_id
            LIMIT 1
        ");
        $stmt->execute([
            ":item_id" => $itemId,
            ":cart_id" => $cartId
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item || $quantity > (int) $item["stock_quantity"]) {
            throw new RuntimeException("Produk tidak ditemukan atau stok tidak cukup.");
        }

        $stmt = $conn->prepare("
            UPDATE cart_items
            SET quantity = :quantity
            WHERE id = :item_id AND cart_id = :cart_id
        ");
        $stmt->execute([
            ":quantity" => $quantity,
            ":item_id" => $itemId,
            ":cart_id" => $cartId
        ]);
    } elseif ($action === "remove") {
        $itemId = filter_var($_POST["item_id"] ?? null, FILTER_VALIDATE_INT);

        if (!$itemId || $itemId < 1) {
            throw new RuntimeException("Item tidak valid.");
        }

        $stmt = $conn->prepare("
            DELETE FROM cart_items
            WHERE id = :item_id AND cart_id = :cart_id
        ");
        $stmt->execute([
            ":item_id" => $itemId,
            ":cart_id" => $cartId
        ]);
    } else {
        throw new RuntimeException("Aksi tidak dikenal.");
    }

    $cartCount = null;
    if ($expectsJson) {
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

    $conn->commit();

    if ($expectsJson) {
        cartActionResponse([
            "success" => true,
            "cart_count" => $cartCount,
            "message" => $action === "add"
                ? "Produk berhasil ditambahkan ke keranjang."
                : "Keranjang berhasil diperbarui."
        ]);
    }

    $separator = strpos($returnTarget, "?") === false ? "?" : "&";
    header("Location: " . $returnTarget . $separator . "success=1");
    exit;
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log($e->getMessage());
    $errorCode = stripos($e->getMessage(), "stok") !== false
        ? "stock"
        : "failed";

    if ($expectsJson) {
        cartActionResponse([
            "success" => false,
            "message" => $errorCode === "stock"
                ? "Jumlah yang diminta melebihi stok produk yang tersedia."
                : "Keranjang gagal diperbarui. Silakan coba lagi."
        ], $errorCode === "stock" ? 409 : 400);
    }

    $separator = strpos($returnTarget, "?") === false ? "?" : "&";
    header("Location: " . $returnTarget . $separator . "error=" . $errorCode);
    exit;
}
