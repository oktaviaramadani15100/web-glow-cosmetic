
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once "../config/database.php";

function respond($success, $message, $statusCode = 200)
{
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message
    ]);

    exit;
}

if (($_SESSION["user_role"] ?? "") !== "admin") {
    respond(false, "Akses ditolak. Silakan login kembali.", 403);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Metode request tidak diizinkan.", 405);
}

$action = $_POST["action"] ?? "";
$productId = filter_var(
    $_POST["product_id"] ?? null,
    FILTER_VALIDATE_INT
);
$quantity = filter_var(
    $_POST["quantity"] ?? null,
    FILTER_VALIDATE_INT
);
$minimum = filter_var(
    $_POST["minimum_stock"] ?? null,
    FILTER_VALIDATE_INT
);

if (!in_array($action, ["add", "edit"], true)) {
    respond(false, "Jenis tindakan tidak valid.", 422);
}

if (
    $productId === false || $productId === null || $productId < 1 ||
    $quantity === false || $quantity === null || $quantity < 0 ||
    $minimum === false || $minimum === null || $minimum < 0
) {
    respond(false, "Data produk dan stok harus valid.", 422);
}

try {
    $conn->beginTransaction();

    $productQuery = $conn->prepare("
        SELECT id
        FROM products
        WHERE id = ? AND status = 'active'
        FOR UPDATE
    ");

    $productQuery->execute([$productId]);

    if (!$productQuery->fetchColumn()) {
        $conn->rollBack();
        respond(false, "Produk tidak ditemukan atau tidak aktif.", 404);
    }

    $stockQuery = $conn->prepare("
        SELECT quantity
        FROM stock
        WHERE product_id = ?
        FOR UPDATE
    ");

    $stockQuery->execute([$productId]);

    $existingStock = $stockQuery->fetchColumn();

    if ($action === "add") {
        // Tambahkan stok ke jumlah yang sudah ada.
        $newQuantity = ($existingStock === false)
            ? $quantity
            : (int)$existingStock + $quantity;
    } else {
        // Edit: ganti stok dengan jumlah yang dimasukkan.
        $newQuantity = $quantity;
    }

    if ($existingStock === false) {
        $saveQuery = $conn->prepare("
            INSERT INTO stock
                (product_id, quantity, minimum_stock, updated_at)
            VALUES (?, ?, ?, NOW())
        ");

        $saveQuery->execute([
            $productId,
            $newQuantity,
            $minimum
        ]);
    } else {
        $saveQuery = $conn->prepare("
            UPDATE stock
            SET quantity = ?,
                minimum_stock = ?,
                updated_at = NOW()
            WHERE product_id = ?
        ");

        $saveQuery->execute([
            $newQuantity,
            $minimum,
            $productId
        ]);
    }

    $conn->commit();

    respond(
        true,
        $action === "add"
            ? "Penambahan stok berhasil disimpan."
            : "Perubahan stok berhasil disimpan."
    );

} catch (Throwable $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log($error->getMessage());

    respond(false, "Gagal menyimpan stok. Periksa database.", 500);
}
