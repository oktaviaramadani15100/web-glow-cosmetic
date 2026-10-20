<?php
session_start();
require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

function cartDataResponse(array $payload, int $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    cartDataResponse(["success" => false, "message" => "Metode permintaan tidak didukung."], 405);
}

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

if ($userId < 1 || !in_array($role, ["customer", "pelanggan"], true)) {
    cartDataResponse(["success" => false, "message" => "Silakan masuk untuk melihat keranjang."], 401);
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$cartStmt = $conn->prepare("
    SELECT id
    FROM carts
    WHERE user_id = :user_id AND status = 'active'
    ORDER BY id DESC
    LIMIT 1
");
$cartStmt->execute([":user_id" => $userId]);
$cartId = (int) $cartStmt->fetchColumn();

$items = [];
$cartCount = 0;
$subtotal = 0;

if ($cartId > 0) {
    $itemStmt = $conn->prepare("
        SELECT
            ci.id AS item_id,
            ci.product_id,
            ci.quantity,
            ci.price,
            p.name,
            p.image,
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
    $itemStmt->execute([":cart_id" => $cartId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item["item_id"] = (int) $item["item_id"];
        $item["product_id"] = (int) $item["product_id"];
        $item["quantity"] = (int) $item["quantity"];
        $item["price"] = (float) $item["price"];
        $item["stock_quantity"] = (int) $item["stock_quantity"];
        $cartCount += $item["quantity"];
        $subtotal += $item["price"] * $item["quantity"];
    }
    unset($item);
}

$shipping = $items ? 10000 : 0;

cartDataResponse([
    "success" => true,
    "csrf_token" => $_SESSION["csrf_token"],
    "cart_count" => $cartCount,
    "items" => $items,
    "subtotal" => $subtotal,
    "shipping" => $shipping,
    "total" => $subtotal + $shipping
]);
