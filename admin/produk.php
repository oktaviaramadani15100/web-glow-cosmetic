<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../customer/login.php");
    exit;
}

$adminId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? 0;

if (!function_exists("e")) {
    function e($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            "UTF-8"
        );
    }
}

$stmt = $conn->prepare("
    SELECT id, name, email, role
    FROM users
    WHERE id = :id AND role = 'admin'
    LIMIT 1
");
$stmt->execute(["id" => $adminId]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("Data admin tidak ditemukan. Silakan login kembali.");
}

$adminName = $admin["name"] ?: "Admin";
$adminRole = $admin["role"] ?: "Administrator";

$hari = [
    "Sunday" => "Minggu",
    "Monday" => "Senin",
    "Tuesday" => "Selasa",
    "Wednesday" => "Rabu",
    "Thursday" => "Kamis",
    "Friday" => "Jumat",
    "Saturday" => "Sabtu"
];

$bulan = [
    1 => "Januari",
    2 => "Februari",
    3 => "Maret",
    4 => "April",
    5 => "Mei",
    6 => "Juni",
    7 => "Juli",
    8 => "Agustus",
    9 => "September",
    10 => "Oktober",
    11 => "November",
    12 => "Desember"
];

date_default_timezone_set("Asia/Jakarta");

$namaHari = $hari[date("l")];
$tanggalHariIni = $namaHari . ", " . date("d") . " " .
    $bulan[(int) date("n")] . " " . date("Y");

$stmt = $conn->query("
    SELECT id, name
    FROM categories
    WHERE LOWER(status) IN ('active', 'aktif')
    ORDER BY name ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("
    SELECT
        p.id,
        p.category_id,
        p.name,
        p.sku,
        p.description,
        p.price,
        p.weight,
        p.image,
        p.status,
        c.name AS category_name,
        COALESCE(s.quantity, 0) AS stock_quantity
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN stock s ON s.product_id = p.id
    ORDER BY p.created_at DESC, p.id DESC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Produk - Glow.Cosmetic</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/admin-pages.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>

    <div class="admin-layout">

        <aside class="sidebar" id="sidebar">

            <div class="sidebar-logo">
                <img src="../img/logo2.png" alt="Glow.Cosmetic">
            </div>

            <nav class="sidebar-menu">

                <a href="dashboard.php" class="menu-item">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>

                <a href="produk.php" class="menu-item active">
                    <i class="fa-solid fa-box"></i>
                    <span>Produk</span>
                </a>

                <a href="kategori.php" class="menu-item">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Kategori</span>
                </a>

                <a href="stok.php" class="menu-item ">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Stok</span>
                </a>

                <a href="pesanan.php" class="menu-item">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Pesanan</span>
                </a>

                <a href="pembayaran.php" class="menu-item">
                    <i class="fa-solid fa-wallet"></i>
                    <span>Pembayaran</span>
                </a>

                <a href="pengiriman.php" class="menu-item">
                    <i class="fa-solid fa-truck"></i>
                    <span>Pengiriman</span>
                </a>

                <a href="user.php" class="menu-item">
                    <i class="fas fa-users"></i>

                    <span>
                        Users
                    </span>
                </a>

                <a href="laporan.php" class="menu-item">
                    <i class="fa-solid fa-chart-column"></i>
                    <span>Laporan</span>
                </a>

                <a href="faq.php" class="menu-item">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>FAQ</span>
                </a>

                <a href="blog.php" class="menu-item">
                    <i class="fa-solid fa-book-open"></i>
                    <span>Blog</span>
                </a>

            </nav>

            <div class="sidebar-bottom">
                <a href="profile.php" class="admin-profile">
                    <div class="admin-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <div class="admin-profile-info">
                        <strong><?= e($adminName) ?></strong>
                        <span><?= e(ucfirst($adminRole)) ?></span>
                    </div>
                </a>
            </div>

        </aside>

        <main class="main-content">

            <header class="top-header">

                <div class="header-left">

                    <button class="mobile-menu-btn" id="menuButton">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div>
                        <span class="breadcrumb">Manajemen</span>
                        <h1>
                            Produk
                        </h1>

                    </div>

                </div>


                <div class="header-right">

                    <span class="date">
                        <i class="fa-regular fa-calendar"></i>
                        <?= e($tanggalHariIni) ?>
                    </span>

                    <button type="button" class="notification-btn" aria-label="Notifikasi">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notification-dot"></span>
                    </button>

                    <a href="profile.php" class="header-avatar" title="<?= e($adminName) ?>">
                        <?= e(strtoupper(substr(trim($adminName), 0, 1))) ?>
                    </a>

                </div>

            </header>


            <div class="page-content">

                <div class="management-card">

                    <div class="page-toolbar">

                        <div class="search-box">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input type="text" id="searchProduct" placeholder="Cari produk...">

                        </div>


                        <div class="toolbar-actions">

                            <button class="btn btn-primary" onclick="openProductModal()">

                                <i class="fa-solid fa-plus"></i>

                                Tambah Produk

                            </button>

                        </div>

                        <div class="modal product-modal" id="productModal" role="dialog" aria-modal="true"
                            aria-labelledby="productModalTitle">

                            <div class="modal-content product-modal-content">

                                <div class="modal-header product-modal-header">

                                    <div>
                                        <h2 id="productModalTitle">Tambah Produk</h2>
                                        <p>Lengkapi informasi produk yang akan ditampilkan di toko.</p>
                                    </div>

                                    <button class="modal-close" aria-label="Tutup modal"
                                        onclick="closeModal('productModal')">

                                        &times;

                                    </button>

                                </div>

                                <form action="../process/produk_process.php" method="POST"
                                    enctype="multipart/form-data">
                                    <input type="hidden" name="action" id="productAction" value="create">
                                    <input type="hidden" name="id" id="productId" value="">

                                    <div class="form-group">
                                        <label for="productName">Nama Produk</label>
                                        <input type="text" id="productName" name="name"
                                            placeholder="Masukkan nama produk" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="productImage">Gambar Produk</label>

                                        <input type="file" id="productImage" name="image"
                                            accept="image/jpeg,image/png,image/webp" required>

                                        <div class="product-image-preview" id="imagePreview">
                                            <img id="previewImg" alt="Pratinjau gambar produk">
                                            <button type="button" class="btn btn-outline"
                                                onclick="removeProductImage()">Hapus gambar</button>
                                        </div>
                                    </div>

                                    <div class="form-row">

                                        <div class="form-group">
                                            <label for="productCategory">Kategori</label>

                                            <select id="productCategory" name="category_id" required>
                                                <option value="">Pilih kategori</option>

                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= (int) $category['id'] ?>">
                                                        <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="productStock">Stok</label>
                                            <input type="number" id="productStock" name="stock" min="0" placeholder="0"
                                                required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="productPrice">Harga (RP)</label>
                                        <input type="number" id="productPrice" name="price" min="1" placeholder="Rp"
                                            required>
                                    </div>

                                    <div class="form-group">
                                        <label>Berat (gram)</label>
                                        <input type="number" id="productWeight" name="weight" min="0" value="0" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="productStatus">Status Produk</label>
                                        <select id="productStatus" name="status">
                                            <option value="active">Aktif</option>
                                            <option value="inactive">Tidak Aktif</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="productDescription">Deskripsi</label>
                                        <textarea id="productDescription" name="description" rows="3"
                                            placeholder="Deskripsi produk"></textarea>
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline"
                                            onclick="closeModal('productModal')">Batal</button>

                                        <button type="submit" class="btn btn-primary" id="productSubmitButton">
                                            <i class="fa-solid fa-check"></i>
                                            <span id="productSubmitText">Simpan Produk</span>
                                        </button>
                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>


                    <div class="table-wrapper">

                        <table class="admin-table">

                            <thead>

                                <tr>

                                    <th>GAMBAR</th>
                                    <th>NAMA PRODUK</th>
                                    <th>KATEGORI</th>
                                    <th>STOK</th>
                                    <th>HARGA</th>
                                    <th>STATUS</th>
                                    <th>AKSI</th>

                                </tr>

                            </thead>

                            <tbody id="productTable">

                                <?php if (empty($products)): ?>

                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 25px;">
                                            Belum ada produk yang tersedia.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($products as $product): ?>

                                        <tr data-product-row>
                                            <td>
                                                <?php if (!empty($product['image'])): ?>
                                                    <img
                                                        src="../<?= htmlspecialchars(
                                                                    $product['image'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>"
                                                        alt="<?= htmlspecialchars(
                                                                    $product['name'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>"
                                                        style="width: 55px; height: 55px; object-fit: cover; border-radius: 8px;">
                                                <?php else: ?>
                                                    Tidak ada gambar
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $product['name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                                <br>
                                                <small>
                                                    SKU:
                                                    <?= htmlspecialchars(
                                                        $product['sku'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </small>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $product['category_name'] ?? 'Kategori tidak tersedia',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= (int) $product['stock_quantity'] ?>
                                            </td>

                                            <td>
                                                Rp <?= number_format(
                                                        (float) $product['price'],
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) ?>
                                            </td>

                                            <td>
                                                <?php $isProductActive = strtolower($product['status']) === 'active'; ?>
                                                <span class="product-status <?= $isProductActive ? 'active' : 'inactive' ?>">
                                                    <span aria-hidden="true"></span>
                                                    <?= $isProductActive ? 'Aktif' : 'Nonaktif' ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div class="action-buttons">

                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-edit"
                                                        data-product="<?= htmlspecialchars(
                                                                            json_encode([
                                                                                'id' => (int) $product['id'],
                                                                                'name' => $product['name'],
                                                                                'category_id' => (int) $product['category_id'],
                                                                                'stock' => (int) $product['stock_quantity'],
                                                                                'price' => (float) $product['price'],
                                                                                'weight' => (int) $product['weight'],
                                                                                'description' => $product['description'] ?? '',
                                                                                'image' => $product['image'] ?? '',
                                                                                'status' => strtolower($product['status'])
                                                                            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP),
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        ) ?>">
                                                        <i class="fa-solid fa-pen"></i>

                                                    </button>

                                                    <form
                                                        action="../process/produk_process.php"
                                                        method="POST"
                                                        onsubmit="return confirm('Yakin ingin menghapus produk ini?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $product['id'] ?>">

                                                        <button type="submit" class="btn btn-sm btn-delete">
                                                            <i class="fa-solid fa-trash"></i>

                                                        </button>
                                                    </form>

                                                </div>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>
                        </table>

                    </div>

                </div>

            </div>

        </main>

    </div>



    <script src="../js/admin-pages.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.getElementById("productModal");
            const form = modal.querySelector("form");

            const actionInput = document.getElementById("productAction");
            const idInput = document.getElementById("productId");
            const title = document.getElementById("productModalTitle");
            const submitText = document.getElementById("productSubmitText");
            const imageInput = document.getElementById("productImage");
            const imagePreview = document.getElementById("imagePreview");
            const previewImg = document.getElementById("previewImg");

            const originalOpenProductModal = window.openProductModal;

            function clearPreview() {
                imageInput.value = "";
                imagePreview.style.display = "none";
                previewImg.removeAttribute("src");
            }

            window.openProductModal = function() {
                if (typeof originalOpenProductModal === "function") {
                    originalOpenProductModal();
                } else {
                    modal.classList.add("active");
                }

                form.reset();
                actionInput.value = "create";
                idInput.value = "";

                title.textContent = "Tambah Produk";
                submitText.textContent = "Simpan Produk";
                imageInput.required = true;

                document.getElementById("productStatus").value = "active";
                clearPreview();
            };

            document.querySelectorAll(".btn-edit[data-product]").forEach(function(button) {
                button.addEventListener("click", function() {
                    let product;

                    try {
                        product = JSON.parse(button.dataset.product);
                    } catch (error) {
                        alert("Data produk tidak dapat dibaca.");
                        return;
                    }

                    window.openProductModal();

                    actionInput.value = "update";
                    idInput.value = product.id;

                    document.getElementById("productName").value = product.name;
                    document.getElementById("productCategory").value = product.category_id;
                    document.getElementById("productStock").value = product.stock;
                    document.getElementById("productPrice").value = product.price;
                    document.getElementById("productWeight").value = product.weight;
                    document.getElementById("productDescription").value = product.description;
                    document.getElementById("productStatus").value =
                        product.status === "inactive" ? "inactive" : "active";

                    title.textContent = "Edit Produk";
                    submitText.textContent = "Simpan Perubahan";

                    imageInput.required = false;
                    imageInput.value = "";

                    if (product.image) {
                        previewImg.src = "../" + product.image;
                        imagePreview.style.display = "block";
                    } else {
                        clearPreview();
                    }
                });
            });

            imageInput.addEventListener("change", function() {
                const file = imageInput.files[0];

                if (!file) {
                    return;
                }

                if (!file.type.startsWith("image/")) {
                    alert("Silakan pilih file gambar.");
                    clearPreview();
                    return;
                }

                previewImg.src = URL.createObjectURL(file);
                imagePreview.style.display = "block";
            });
        });
    </script>

</body>

</html>