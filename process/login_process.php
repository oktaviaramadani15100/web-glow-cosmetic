<?php

session_start();

require_once "../config/database.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../customer/login.php");
    exit;
}


$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";


if ($email === "" || $password === "") {
    header("Location: ../customer/login.php?error=empty");
    exit;
}


$query = "
    SELECT *
    FROM users
    WHERE email = :email
       OR phone = :email
    LIMIT 1
";


$stmt = $conn->prepare($query);

$stmt->execute([
    ":email" => $email
]);


$user = $stmt->fetch();


if (!$user) {

    die("User tidak ditemukan. Email/No. HP tidak ada di database.");

}


if (!password_verify($password, $user["password"])) {

    die("User ditemukan, tetapi password tidak cocok.");

}


if ($user["status"] !== "active") {

    die("Akun ditemukan tetapi status akun bukan active.");

}


$_SESSION["user_id"] = $user["id"];
$_SESSION["user_name"] = $user["name"];
$_SESSION["user_email"] = $user["email"];
$_SESSION["user_phone"] = $user["phone"];
$_SESSION["user_role"] = $user["role"];


if ($user["role"] === "admin") {

    header("Location: ../admin/dashboard.php");
    exit;

}


header("Location: ../customer/home.php");
exit;