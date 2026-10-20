<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// =====================================================
// 1. CEK AKSES DAN REQUEST
// =====================================================

if (($_SESSION["user_role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Silakan login sebagai admin.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../admin/produk.php");
    exit;
}

$action = $_POST["action"] ?? "";

// =====================================================
// 2. FUNGSI UPLOAD GAMBAR
// =====================================================

function uploadProductImage(): array
{
    if (
        !isset($_FILES["image"]) ||
        $_FILES["image"]["error"] !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException("Gambar gagal diunggah.");
    }

    if ($_FILES["image"]["size"] > 2 * 1024 * 1024) {
        throw new RuntimeException("Ukuran gambar maksimal 2 MB.");
    }

    $tmpFile = $_FILES["image"]["tmp_name"];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpFile);

    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    if (
        !isset($allowedTypes[$mime]) ||
        @getimagesize($tmpFile) === false
    ) {
        throw new RuntimeException(
            "Format gambar harus JPG, PNG, atau WEBP."
        );
    }

    $uploadDir = __DIR__ . "/../uploads/products/";

    if (
        !is_dir($uploadDir) &&
        !mkdir($uploadDir, 0755, true) &&
        !is_dir($uploadDir)
    ) {
        throw new RuntimeException(
            "Folder upload gambar tidak dapat dibuat."
        );
    }

    $fileName = bin2hex(random_bytes(16))
        . "."
        . $allowedTypes[$mime];

    $filePath = $uploadDir . $fileName;
    $relativePath = "uploads/products/" . $fileName;

    if (!move_uploaded_file($tmpFile, $filePath)) {
        throw new RuntimeException("Gagal menyimpan gambar produk.");
    }

    return [
        "path" => $relativePath,
        "full_path" => $filePath
    ];
}

// =====================================================
// 3. AKSI HAPUS PRODUK
// =====================================================

if ($action === "delete") {
    $id = filter_var(
        $_POST["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        header("Location: ../admin/produk.php?error=invalid_id");
        exit;
    }

    try {
        $conn->beginTransaction();

        $find = $conn->prepare("
            SELECT image
            FROM products
            WHERE id = :id
            FOR UPDATE
        ");
        $find->execute([":id" => $id]);

        $product = $find->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $conn->rollBack();
            header("Location: ../admin/produk.php?error=not_found");
            exit;
        }

        // Hapus stok terlebih dahulu
        $deleteStock = $conn->prepare("
            DELETE FROM stock
            WHERE product_id = :id
        ");
        $deleteStock->execute([":id" => $id]);

        // Hapus data produk
        $deleteProduct = $conn->prepare("
            DELETE FROM products
            WHERE id = :id
        ");
        $deleteProduct->execute([":id" => $id]);

        $conn->commit();

        // Hapus file gambar jika berasal dari folder upload produk
        $image = $product["image"] ?? "";

        if (
            $image !== "" &&
            strpos($image, "uploads/products/") === 0 &&
            basename($image) === substr(
                $image,
                strlen("uploads/products/")
            )
        ) {
            $imagePath = __DIR__ . "/../" . $image;

            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }

        header("Location: ../admin/produk.php?success=deleted");
        exit;

    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log($e->getMessage());
        http_response_code(500);

        exit(
            "Produk gagal dihapus. Bisa jadi produk sudah digunakan "
            . "oleh data transaksi lain."
        );
    }
}

// =====================================================
// 4. VALIDASI AKSI TAMBAH DAN EDIT
// =====================================================

if (!in_array($action, ["create", "update"], true)) {
    header("Location: ../admin/produk.php?error=invalid_action");
    exit;
}

$id = filter_var(
    $_POST["id"] ?? null,
    FILTER_VALIDATE_INT
);

$name = trim($_POST["name"] ?? "");

$categoryId = filter_var(
    $_POST["category_id"] ?? null,
    FILTER_VALIDATE_INT
);

$stock = filter_var(
    $_POST["stock"] ?? null,
    FILTER_VALIDATE_INT
);

$price = filter_var(
    $_POST["price"] ?? null,
    FILTER_VALIDATE_INT
);

$weight = filter_var(
    $_POST["weight"] ?? 0,
    FILTER_VALIDATE_INT
);

$description = trim($_POST["description"] ?? "");

$status = $action === "create"
    ? "active"
    : strtolower(trim($_POST["status"] ?? ""));

if (
    ($action === "update" && (!$id || $id < 1)) ||
    $name === "" ||
    $categoryId === false ||
    $categoryId === null ||
    $categoryId < 1 ||
    $stock === false ||
    $stock === null ||
    $stock < 0 ||
    $price === false ||
    $price === null ||
    $price <= 0 ||
    $weight === false ||
    $weight === null ||
    $weight < 0 ||
    !in_array($status, ["active", "inactive"], true)
) {
    exit("Data produk tidak valid. Periksa kembali formulir.");
}

// =====================================================
// 5. SIMPAN DATA PRODUK
// =====================================================

$newImagePath = null;

try {
    // Pastikan kategori tersedia dan aktif
    $categoryCheck = $conn->prepare("
        SELECT id
        FROM categories
        WHERE id = :id
          AND LOWER(status) IN ('active', 'aktif')
        LIMIT 1
    ");

    $categoryCheck->execute([
        ":id" => $categoryId
    ]);

    if (!$categoryCheck->fetchColumn()) {
        exit("Kategori tidak ditemukan atau tidak aktif.");
    }

    $oldImage = "";

    // Untuk edit, ambil data produk lama
    if ($action === "update") {
        $find = $conn->prepare("
            SELECT id, image
            FROM products
            WHERE id = :id
        ");

        $find->execute([":id" => $id]);
        $oldProduct = $find->fetch(PDO::FETCH_ASSOC);

        if (!$oldProduct) {
            exit("Produk tidak ditemukan.");
        }

        $oldImage = $oldProduct["image"] ?? "";
    }

    $newImage = $oldImage;

    // Gambar wajib untuk produk baru.
    // Saat edit, gambar lama tetap dipakai jika tidak diganti.
    $hasNewImage = isset($_FILES["image"])
        && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE;

    if ($action === "create" || $hasNewImage) {
        $uploaded = uploadProductImage();
        $newImage = $uploaded["path"];
        $newImagePath = $uploaded["full_path"];
    }

    $conn->beginTransaction();

    if ($action === "create") {
        // Buat SKU otomatis
        $sku = "GC-" . strtoupper(bin2hex(random_bytes(5)));

        $stmt = $conn->prepare("
            INSERT INTO products (
                category_id,
                name,
                sku,
                description,
                price,
                weight,
                image,
                status,
                created_at,
                updated_at
            ) VALUES (
                :category_id,
                :name,
                :sku,
                :description,
                :price,
                :weight,
                :image,
                'active',
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ":category_id" => $categoryId,
            ":name" => $name,
            ":sku" => $sku,
            ":description" => $description,
            ":price" => $price,
            ":weight" => $weight,
            ":image" => $newImage
        ]);

        $productId = (int) $conn->lastInsertId();

        $stockStmt = $conn->prepare("
            INSERT INTO stock (
                product_id,
                quantity,
                updated_at
            ) VALUES (
                :product_id,
                :quantity,
                NOW()
            )
        ");

        $stockStmt->execute([
            ":product_id" => $productId,
            ":quantity" => $stock
        ]);

    } else {
        // Perbarui produk yang sudah ada
        $update = $conn->prepare("
            UPDATE products
            SET category_id = :category_id,
                name = :name,
                description = :description,
                price = :price,
                weight = :weight,
                image = :image,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            ":category_id" => $categoryId,
            ":name" => $name,
            ":description" => $description,
            ":price" => $price,
            ":weight" => $weight,
            ":image" => $newImage,
            ":status" => $status,
            ":id" => $id
        ]);

        // Perbarui stok atau buat baris stok jika belum ada
        $checkStock = $conn->prepare("
            SELECT product_id
            FROM stock
            WHERE product_id = :id
        ");
        $checkStock->execute([":id" => $id]);

        if ($checkStock->fetchColumn()) {
            $stockStmt = $conn->prepare("
                UPDATE stock
                SET quantity = :quantity,
                    updated_at = NOW()
                WHERE product_id = :id
            ");

            $stockStmt->execute([
                ":quantity" => $stock,
                ":id" => $id
            ]);
        } else {
            $stockStmt = $conn->prepare("
                INSERT INTO stock (
                    product_id,
                    quantity,
                    updated_at
                ) VALUES (
                    :id,
                    :quantity,
                    NOW()
                )
            ");

            $stockStmt->execute([
                ":id" => $id,
                ":quantity" => $stock
            ]);
        }
    }

    $conn->commit();

    if (
        $action === "update" &&
        $newImagePath !== null &&
        $oldImage !== "" &&
        strpos($oldImage, "uploads/products/") === 0 &&
        basename($oldImage) === substr(
            $oldImage,
            strlen("uploads/products/")
        )
    ) {
        $oldImagePath = __DIR__ . "/../" . $oldImage;

        if (is_file($oldImagePath)) {
            unlink($oldImagePath);
        }
    }

    $success = $action === "create" ? "created" : "updated";

    header("Location: ../admin/produk.php?success=" . $success);
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Bersihkan gambar baru jika penyimpanan gagal
    if ($newImagePath !== null && is_file($newImagePath)) {
        unlink($newImagePath);
    }

    error_log($e->getMessage());
    http_response_code(500);

    exit(
        "Produk gagal disimpan. Periksa log PHP untuk mengetahui penyebabnya."
    );
}