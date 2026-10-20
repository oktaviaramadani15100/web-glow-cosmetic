
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../admin/pembayaran.php");
    exit;
}

$csrfToken = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    header("Location: ../admin/pembayaran.php?error=csrf");
    exit;
}

$paymentId = filter_var(
    $_POST["payment_id"] ?? null,
    FILTER_VALIDATE_INT
);

$action = $_POST["action"] ?? "";

if (
    !$paymentId ||
    $paymentId < 1 ||
    !in_array($action, ["verify", "reject"], true)
) {
    header("Location: ../admin/pembayaran.php?error=invalid_data");
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.order_id,
            p.status,
            o.payment_status AS order_payment_status,
            o.order_status
        FROM payments p
        INNER JOIN orders o ON o.id = p.order_id
        WHERE p.id = :payment_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([":payment_id" => $paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new RuntimeException("not_found");
    }

    if (
        $payment["status"] !== "pending" ||
        $payment["order_payment_status"] === "paid" ||
        strtolower($payment["order_status"]) === "cancelled"
    ) {
        throw new RuntimeException("already_processed");
    }

    if ($action === "verify") {
        // Simulasi verifikasi pembayaran oleh admin.
        $updatePayment = $conn->prepare("
            UPDATE payments
            SET status = 'verified',
                paid_at = NOW()
            WHERE id = :payment_id
              AND status = 'pending'
        ");

        $updatePayment->execute([
            ":payment_id" => $paymentId
        ]);

        if ($updatePayment->rowCount() !== 1) {
            throw new RuntimeException("update_failed");
        }

        $updateOrder = $conn->prepare("
            UPDATE orders
            SET payment_status = 'paid'
            WHERE id = :order_id
        ");

        $updateOrder->execute([
            ":order_id" => $payment["order_id"]
        ]);

        $conn->commit();

        header("Location: ../admin/pembayaran.php?success=verified");
        exit;
    }

    // Penolakan pembayaran tidak mengisi paid_at.
    $updatePayment = $conn->prepare("
        UPDATE payments
        SET status = 'rejected',
            paid_at = NULL
        WHERE id = :payment_id
          AND status = 'pending'
    ");

    $updatePayment->execute([
        ":payment_id" => $paymentId
    ]);

    if ($updatePayment->rowCount() !== 1) {
        throw new RuntimeException("update_failed");
    }

    $updateOrder = $conn->prepare("
        UPDATE orders
        SET payment_status = 'unpaid'
        WHERE id = :order_id
    ");

    $updateOrder->execute([
        ":order_id" => $payment["order_id"]
    ]);

    $conn->commit();

    header("Location: ../admin/pembayaran.php?success=rejected");
    exit;

} catch (Throwable $error) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Payment verification error: " . $error->getMessage());

    $errorCode = $error instanceof RuntimeException
        ? $error->getMessage()
        : "server_error";

    $allowedErrors = [
        "not_found",
        "already_processed",
        "update_failed"
    ];

    if (!in_array($errorCode, $allowedErrors, true)) {
        $errorCode = "server_error";
    }

    header(
        "Location: ../admin/pembayaran.php?error=" .
        urlencode($errorCode)
    );
    exit;
}
