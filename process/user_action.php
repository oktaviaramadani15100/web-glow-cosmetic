
<?php
session_start();
require_once "../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

function redirectWithMessage($message, $type = "success")
{
    $_SESSION["user_message"] = $message;
    $_SESSION["user_message_type"] = $type;

    header("Location: ../admin/user.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectWithMessage(
        "Permintaan tidak valid. Silakan ulangi dari halaman User.",
        "error"
    );
}

$sessionToken = $_SESSION["csrf_token"] ?? "";
$formToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($sessionToken) ||
    !is_string($formToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $formToken)
) {
    redirectWithMessage(
        "Sesi keamanan sudah tidak valid. Muat ulang halaman dan coba lagi.",
        "error"
    );
}

$currentUserId = (int)($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);

$action = $_POST["action"] ?? "";
$id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$password = $_POST["password"] ?? "";
$status = $_POST["status"] ?? "active";

if (!in_array($action, ["add", "edit", "delete"], true)) {
    redirectWithMessage("Jenis tindakan tidak dikenal.", "error");
}

try {

    if ($action === "add") {
        if ($name === "" || strlen($name) > 100) {
            redirectWithMessage(
                "Nama wajib diisi dan maksimal 100 karakter.",
                "error"
            );
        }

        if (
            $email === "" ||
            strlen($email) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            redirectWithMessage(
                "Masukkan alamat email yang valid.",
                "error"
            );
        }

        if (strlen($phone) > 20) {
            redirectWithMessage(
                "Nomor telepon maksimal 20 karakter.",
                "error"
            );
        }

        if (!is_string($password) || strlen($password) < 8) {
            redirectWithMessage(
                "Password admin minimal 8 karakter.",
                "error"
            );
        }

        $checkEmail = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $checkEmail->execute(["email" => $email]);

        if ($checkEmail->fetch(PDO::FETCH_ASSOC)) {
            redirectWithMessage(
                "Email sudah digunakan akun lain. Gunakan email berbeda.",
                "error"
            );
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insert = $conn->prepare("
            INSERT INTO users
                (name, email, password, phone, role, status)
            VALUES
                (:name, :email, :password, :phone, 'admin', 'active')
        ");

        $insert->execute([
            "name" => $name,
            "email" => $email,
            "password" => $hashedPassword,
            "phone" => $phone !== "" ? $phone : null
        ]);

        redirectWithMessage(
            "Akun Admin untuk " . $name .
            " berhasil dibuat dan siap digunakan untuk login.",
            "success"
        );
    }

    if ($action === "edit") {
        if (!$id || $id < 1) {
            redirectWithMessage("ID user tidak valid.", "error");
        }

        if ($name === "" || strlen($name) > 100) {
            redirectWithMessage(
                "Nama wajib diisi dan maksimal 100 karakter.",
                "error"
            );
        }

        if (
            $email === "" ||
            strlen($email) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            redirectWithMessage(
                "Masukkan alamat email yang valid.",
                "error"
            );
        }

        if (strlen($phone) > 20) {
            redirectWithMessage(
                "Nomor telepon maksimal 20 karakter.",
                "error"
            );
        }

        if (!in_array($status, ["active", "inactive"], true)) {
            redirectWithMessage("Status user tidak valid.", "error");
        }

        $checkUser = $conn->prepare("
            SELECT id
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $checkUser->execute(["id" => $id]);

        if (!$checkUser->fetch(PDO::FETCH_ASSOC)) {
            redirectWithMessage("Akun yang ingin diedit tidak ditemukan.", "error");
        }

        // Pastikan email tidak dipakai akun lain.
        $checkEmail = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = :email AND id <> :id
            LIMIT 1
        ");
        $checkEmail->execute([
            "email" => $email,
            "id" => $id
        ]);

        if ($checkEmail->fetch(PDO::FETCH_ASSOC)) {
            redirectWithMessage(
                "Email tersebut sudah digunakan akun lain. Gunakan email berbeda.",
                "error"
            );
        }

        if (!is_string($password)) {
            redirectWithMessage("Password tidak valid.", "error");
        }

        if ($password !== "" && strlen($password) < 8) {
            redirectWithMessage(
                "Password baru minimal 8 karakter.",
                "error"
            );
        }

        if ($password !== "") {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $update = $conn->prepare("
                UPDATE users
                SET name = :name,
                    email = :email,
                    phone = :phone,
                    password = :password,
                    status = :status
                WHERE id = :id
            ");

            $update->execute([
                "name" => $name,
                "email" => $email,
                "phone" => $phone !== "" ? $phone : null,
                "password" => $hashedPassword,
                "status" => $status,
                "id" => $id
            ]);
        } else {
            $update = $conn->prepare("
                UPDATE users
                SET name = :name,
                    email = :email,
                    phone = :phone,
                    status = :status
                WHERE id = :id
            ");

            $update->execute([
                "name" => $name,
                "email" => $email,
                "phone" => $phone !== "" ? $phone : null,
                "status" => $status,
                "id" => $id
            ]);
        }

        redirectWithMessage(
            "Data akun " . $name .
            " berhasil diperbarui. Perubahan sudah disimpan.",
            "success"
        );
    }

    if ($action === "delete") {
        if (!$id || $id < 1) {
            redirectWithMessage("ID user tidak valid.", "error");
        }

        if ($currentUserId < 1) {
            redirectWithMessage(
                "ID admin yang sedang login tidak ditemukan. Periksa session login.",
                "error"
            );
        }

        if ($id === $currentUserId) {
            redirectWithMessage(
                "Akun yang sedang kamu gunakan tidak dapat dihapus.",
                "error"
            );
        }

        $checkUser = $conn->prepare("
            SELECT id, name, role
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $checkUser->execute(["id" => $id]);
        $user = $checkUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            redirectWithMessage(
                "Akun tidak ditemukan atau sudah dihapus.",
                "error"
            );
        }

        $delete = $conn->prepare("
            DELETE FROM users
            WHERE id = :id
        ");
        $delete->execute(["id" => $id]);

        redirectWithMessage(
            "Akun " . $user["name"] .
            " berhasil dihapus dari sistem.",
            "success"
        );
    }

} catch (PDOException $error) {
    error_log($error->getMessage());
    if ($error->getCode() === "23000") {
        if ($action === "delete") {
            redirectWithMessage(
                "Akun tidak dapat dihapus karena masih terhubung dengan data lain, misalnya pesanan. Nonaktifkan akun jika masih diperlukan.",
                "error"
            );
        }

        redirectWithMessage(
            "Data tidak dapat disimpan karena email sudah digunakan atau ada hubungan data yang tidak sesuai.",
            "error"
        );
    }

    redirectWithMessage(
        "Terjadi kesalahan database. Periksa data dan coba kembali.",
        "error"
    );
}
