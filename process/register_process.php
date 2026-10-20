<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../register.php");
    exit;
}

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirm-password"] ?? "";


if ($name === "" || $email === "" || $phone === "" || $password === "" || $confirmPassword === "") {
    die("Semua data harus diisi.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Format email tidak valid.");
}

if ($password !== $confirmPassword) {
    die("Password dan konfirmasi password tidak sama.");
}

if (strlen($password) < 6) {
    die("Password minimal 6 karakter.");
}

$checkQuery = "SELECT id FROM users WHERE email = :email LIMIT 1";

$checkStmt = $conn->prepare($checkQuery);
$checkStmt->execute([
    ":email" => $email
]);

if ($checkStmt->fetch()) {
    die("Email sudah terdaftar. Silakan gunakan email lain.");
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$role = "customer";

$query = "
    INSERT INTO users (
        name,
        email,
        phone,
        password,
        role,
        status,
        created_at
    )
    VALUES (
        :name,
        :email,
        :phone,
        :password,
        :role,
        :status,
        NOW()
    )
";

$stmt = $conn->prepare($query);

$stmt->execute([
    ":name" => $name,
    ":email" => $email,
    ":phone" => $phone,
    ":password" => $hashedPassword,
    ":role" => $role,
    ":status" => "active"
]);

header("Location: ../customer/login.php?register=success");
exit;
