
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Silakan login sebagai admin.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../admin/kategori.php");
    exit;
}

$action = $_POST["action"] ?? "";
$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);

function kembali(string $hasil): void
{
    header("Location: ../admin/kategori.php?result=" . urlencode($hasil));
    exit;
}

try {
    if ($action === "create" || $action === "update") {
        $name = trim($_POST["name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $status = $_POST["status"] ?? "active";

        if ($name === "" || mb_strlen($name) > 100) {
            kembali("invalid");
        }

        if (!in_array($status, ["active", "inactive"], true)) {
            kembali("invalid");
        }

        if ($action === "create") {
            $sql = "INSERT INTO categories
                    (name, description, status, created_at)
                    VALUES (:name, :description, :status, NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ":name" => $name,
                ":description" => $description,
                ":status" => $status
            ]);

            kembali("created");
        }

        if (!$id || $id < 1) {
            kembali("invalid");
        }

        $stmt = $conn->prepare(
            "UPDATE categories
             SET name = :name,
                 description = :description,
                 status = :status
             WHERE id = :id"
        );

        $stmt->execute([
            ":name" => $name,
            ":description" => $description,
            ":status" => $status,
            ":id" => $id
        ]);

        kembali("updated");
    }

    if ($action === "delete") {
        if (!$id || $id < 1) {
            kembali("invalid");
        }

        // Cegah kategori dihapus jika masih dipakai produk.
        $check = $conn->prepare(
            "SELECT COUNT(*) FROM products WHERE category_id = :id"
        );
        $check->execute([":id" => $id]);

        if ((int) $check->fetchColumn() > 0) {
            kembali("in_use");
        }

        $stmt = $conn->prepare(
            "DELETE FROM categories WHERE id = :id"
        );
        $stmt->execute([":id" => $id]);

        kembali($stmt->rowCount() > 0 ? "deleted" : "not_found");
    }

    kembali("invalid");

} catch (PDOException $e) {
    error_log($e->getMessage());
    kembali("error");
}
