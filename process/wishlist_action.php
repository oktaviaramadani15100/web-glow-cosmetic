<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: home.php");
    exit;
}

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

if ($userId < 1 || !in_array($role, ["customer", "pelanggan"], true)) {
    header("Location: ../customer/login.php");
    exit;
}

$token = (string) ($_POST["csrf_token"] ?? "");

if (
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $token)
) {
    exit("Permintaan tidak valid. Silakan kembali dan coba lagi.");
}

$productId = filter_var($_POST["product_id"] ?? null, FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";

if (!$productId || $productId < 1) {
    header("Location: ../customer/wishlist.php");
    exit;
}

$returnTo = (string) ($_POST["return_to"] ?? "");

$allowedPaths = [
    "home.php" => "../customer/home.php",
    "produk.php" => "../customer/produk.php",
    "wishlist.php" => "../customer/wishlist.php",
    "detail_produk.php" => "../customer/detail_produk.php"
];

$path = parse_url($returnTo, PHP_URL_PATH);

if (
    !isset($allowedPaths[$path]) ||
    str_contains($returnTo, "\\") ||
    str_contains($returnTo, "\r") ||
    str_contains($returnTo, "\n")
) {
    $returnUrl = "../customer/wishlist.php";
} else {
    $query = parse_url($returnTo, PHP_URL_QUERY);
    $returnUrl = $allowedPaths[$path] . ($query ? "?" . $query : "");
}


try {
    if ($action === "add") {
        $checkProduct = $conn->prepare("
            SELECT id FROM products
            WHERE id = :id
              AND LOWER(status) IN ('active', 'aktif')
        ");
        $checkProduct->execute([":id" => $productId]);

        if (!$checkProduct->fetch()) {
            header("Location: ../customer/produk.php");
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO wishlists (user_id, product_id)
            SELECT :user_id, :product_id
            WHERE NOT EXISTS (
                SELECT 1 FROM wishlists
                WHERE user_id = :check_user
                  AND product_id = :check_product
            )
        ");
        $stmt->execute([
            ":user_id" => $userId,
            ":product_id" => $productId,
            ":check_user" => $userId,
            ":check_product" => $productId
        ]);

        header("Location: " . $returnUrl);
        exit;
    }

    if ($action === "remove") {
        $stmt = $conn->prepare("
            DELETE FROM wishlists
            WHERE user_id = :user_id AND product_id = :product_id
        ");
        $stmt->execute([
            ":user_id" => $userId,
            ":product_id" => $productId
        ]);
        header("Location: " . $returnUrl);
        exit;
    }

    header("Location: ../customer/wishlist.php");
    exit;
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Wishlist gagal diproses. Silakan coba lagi.");
}
