<?php

$host = "localhost";
$port = "3307";
$dbname = "db_glow_cosmetic";
$username = "root";
$password = "";

try {
    $conn = new PDO(
        "mysql:host=$host;
        port=$port;
        dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}