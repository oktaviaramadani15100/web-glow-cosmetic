
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../admin/pesanan.php");
    exit;
}

$csrfToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($csrfToken) ||
    !isset($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    http_response_code(403);
    exit("Permintaan tidak valid. Silakan kembali dan coba lagi.");
}

$id = filter_var(
    $_POST["id"] ?? null,
    FILTER_VALIDATE_INT
);

$status = $_POST["order_status"] ?? "";

$allowedStatuses = [
    "pending",
    "processing",
    "shipped",
    "completed",
    "cancelled"
];

if (!$id || $id < 1 || !in_array($status, $allowedStatuses, true)) {
    header("Location: ../admin/pesanan.php?error=invalid");
    exit;
}

try {
    $stmt = $conn->prepare("
        UPDATE orders
        SET order_status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ":status" => $status,
        ":id" => $id
    ]);

    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare("
            SELECT id FROM orders WHERE id = :id LIMIT 1
        ");
        $check->execute([":id" => $id]);

        if (!$check->fetchColumn()) {
            header("Location: ../admin/pesanan.php?error=not_found");
            exit;
        }
    }

    header("Location: ../admin/pesanan.php?success=updated");
    exit;

} catch (PDOException $error) {
    error_log($error->getMessage());

    header("Location: ../admin/pesanan.php?error=update_failed");
    exit;
}
