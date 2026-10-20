
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../admin/pengiriman.php");
    exit;
}

$csrfToken = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    header("Location: ../admin/pengiriman.php?error=csrf");
    exit;
}

$shipmentId = filter_var(
    $_POST["shipment_id"] ?? null,
    FILTER_VALIDATE_INT
);

$trackingNumber = trim($_POST["tracking_number"] ?? "");
$status = $_POST["status"] ?? "";

$allowedStatuses = [
    "waiting",
    "processing",
    "shipped",
    "delivered",
    "failed"
];

if (
    !$shipmentId ||
    $shipmentId < 1 ||
    !in_array($status, $allowedStatuses, true) ||
    strlen($trackingNumber) > 100
) {
    header("Location: ../admin/pengiriman.php?error=invalid_data");
    exit;
}

// Status Dalam Perjalanan/Selesai membutuhkan nomor resi.
if (
    in_array($status, ["shipped", "delivered"], true) &&
    $trackingNumber === ""
) {
    header("Location: ../admin/pengiriman.php?error=tracking_required");
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT id, status
        FROM shipments
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([":id" => $shipmentId]);
    $shipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shipment) {
        throw new RuntimeException("not_found");
    }

    $shippedAt = null;
    $deliveredAt = null;

    // Pertahankan waktu ketika status sudah pernah dicapai.
    if (in_array($status, ["shipped", "delivered"], true)) {
        $shippedAt = date("Y-m-d H:i:s");
    }

    if ($status === "delivered") {
        $deliveredAt = date("Y-m-d H:i:s");
    }

    $update = $conn->prepare("
        UPDATE shipments
        SET tracking_number = :tracking_number,
            status = :status,
            shipped_at = CASE
                WHEN :status_shipped IN ('shipped', 'delivered')
                    THEN COALESCE(shipped_at, :shipped_at)
                ELSE shipped_at
            END,
            delivered_at = CASE
                WHEN :status_delivered = 'delivered'
                    THEN COALESCE(delivered_at, :delivered_at)
                ELSE delivered_at
            END
        WHERE id = :id
    ");

    $update->execute([
        ":tracking_number" => $trackingNumber !== ""
            ? $trackingNumber
            : null,
        ":status" => $status,
        ":status_shipped" => $status,
        ":shipped_at" => $shippedAt,
        ":status_delivered" => $status,
        ":delivered_at" => $deliveredAt,
        ":id" => $shipmentId
    ]);

    $conn->commit();

    header("Location: ../admin/pengiriman.php?success=updated");
    exit;

} catch (Throwable $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Shipping update error: " . $error->getMessage());

    $code = $error instanceof RuntimeException &&
        $error->getMessage() === "not_found"
        ? "not_found"
        : "update_failed";

    header("Location: ../admin/pengiriman.php?error=" . $code);
    exit;
}

