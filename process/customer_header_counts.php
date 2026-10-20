<?php
session_start();

require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

$counts = [
    "wishlist" => 0,
    "cart" => 0
];

if ($userId > 0 && in_array($role, ["customer", "pelanggan"], true)) {
    $wishlistStmt = $conn->prepare("
        SELECT COUNT(*)
        FROM wishlists
        WHERE user_id = :user_id
    ");
    $wishlistStmt->execute([":user_id" => $userId]);
    $counts["wishlist"] = (int) $wishlistStmt->fetchColumn();

    $cartStmt = $conn->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0)
        FROM carts c
        INNER JOIN cart_items ci ON ci.cart_id = c.id
        WHERE c.user_id = :user_id
          AND c.status = 'active'
    ");
    $cartStmt->execute([":user_id" => $userId]);
    $counts["cart"] = (int) $cartStmt->fetchColumn();
}

echo json_encode($counts, JSON_THROW_ON_ERROR);
