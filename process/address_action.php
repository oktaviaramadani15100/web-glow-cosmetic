
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

$userId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);
$role = strtolower($_SESSION["user_role"] ?? $_SESSION["role"] ?? "");

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    $userId < 1 ||
    !in_array($role, ["customer", "pelanggan"], true)
) {
    header("Location: ../customer/login.php");
    exit;
}

$token = $_POST["csrf_token"] ?? "";

if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($token) ||
    !hash_equals($_SESSION["csrf_token"], $token)
) {
    exit("Permintaan tidak valid. Muat ulang halaman akun.");
}

$action = $_POST["action"] ?? "save";

function backToAddress(string $message): never
{
    header(
        "Location: ../customer/akun.php?section=address&message=" .
            urlencode($message)
    );
    exit;
}

try {
    if ($action === "delete") {
        $addressId = filter_var(
            $_POST["address_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$addressId || $addressId < 1) {
            backToAddress("invalid");
        }

        $stmt = $conn->prepare("
            DELETE FROM user_addresses
            WHERE id = :id AND user_id = :user_id
        ");

        $stmt->execute([
            ":id" => $addressId,
            ":user_id" => $userId
        ]);

        backToAddress(
            $stmt->rowCount() ? "deleted" : "not_found"
        );
    }

    if ($action === "default") {
        $addressId = filter_var(
            $_POST["address_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$addressId || $addressId < 1) {
            backToAddress("invalid");
        }

        $stmt = $conn->prepare("
            SELECT id FROM user_addresses
            WHERE id = :id AND user_id = :user_id
        ");

        $stmt->execute([
            ":id" => $addressId,
            ":user_id" => $userId
        ]);

        if (!$stmt->fetch()) {
            backToAddress("not_found");
        }

        $conn->beginTransaction();

        $stmt = $conn->prepare("
            UPDATE user_addresses
            SET is_default = 0
            WHERE user_id = :user_id
        ");
        $stmt->execute([":user_id" => $userId]);

        $stmt = $conn->prepare("
            UPDATE user_addresses
            SET is_default = 1
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            ":id" => $addressId,
            ":user_id" => $userId
        ]);

        $conn->commit();

        backToAddress("default");
    }

    if ($action !== "save") {
        backToAddress("invalid");
    }

    $addressId = filter_var(
        $_POST["address_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $name = trim($_POST["recipient_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $city = trim($_POST["city"] ?? "");
    $province = trim($_POST["province"] ?? "");
    $postal = trim($_POST["postal_code"] ?? "");
    $isDefault = isset($_POST["is_default"]) ? 1 : 0;

    if (
        $name === "" ||
        $phone === "" ||
        $address === "" ||
        $city === "" ||
        $province === "" ||
        $postal === ""
    ) {
        backToAddress("incomplete");
    }

    if (
        mb_strlen($name) > 150 ||
        mb_strlen($phone) > 30 ||
        mb_strlen($address) > 1000 ||
        mb_strlen($city) > 100 ||
        mb_strlen($province) > 100 ||
        mb_strlen($postal) > 20
    ) {
        backToAddress("invalid");
    }

    if ($addressId !== false && $addressId !== null) {
        if ($addressId < 1) {
            backToAddress("invalid");
        }

        $stmt = $conn->prepare("
            SELECT id FROM user_addresses
            WHERE id = :id AND user_id = :user_id
        ");

        $stmt->execute([
            ":id" => $addressId,
            ":user_id" => $userId
        ]);

        if (!$stmt->fetch()) {
            backToAddress("not_found");
        }
    }

    $conn->beginTransaction();

    if ($isDefault) {
        $stmt = $conn->prepare("
            UPDATE user_addresses
            SET is_default = 0
            WHERE user_id = :user_id
        ");
        $stmt->execute([":user_id" => $userId]);
    }

    if ($addressId !== false && $addressId !== null) {
        $stmt = $conn->prepare("
            UPDATE user_addresses
            SET recipient_name = :name,
                phone = :phone,
                address = :address,
                city = :city,
                province = :province,
                postal_code = :postal,
                is_default = :is_default
            WHERE id = :id AND user_id = :user_id
        ");

        $stmt->execute([
            ":name" => $name,
            ":phone" => $phone,
            ":address" => $address,
            ":city" => $city,
            ":province" => $province,
            ":postal" => $postal,
            ":is_default" => $isDefault,
            ":id" => $addressId,
            ":user_id" => $userId
        ]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO user_addresses
                (user_id, recipient_name, phone, address,
                 city, province, postal_code, is_default)
            VALUES
                (:user_id, :name, :phone, :address,
                 :city, :province, :postal, :is_default)
        ");

        $stmt->execute([
            ":user_id" => $userId,
            ":name" => $name,
            ":phone" => $phone,
            ":address" => $address,
            ":city" => $city,
            ":province" => $province,
            ":postal" => $postal,
            ":is_default" => $isDefault
        ]);
    }

    $conn->commit();

    backToAddress("saved");
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Address action error: " . $e->getMessage());
    http_response_code(500);
    exit("Alamat belum berhasil disimpan. Periksa log PHP untuk detailnya.");
}
