<?php
session_start();
require_once "../config/database.php";

if (($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
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

$currentUserId = (int)($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}


try {
    $query = $conn->prepare("
        SELECT id, name, email, phone, role, status, created_at
        FROM users
        ORDER BY created_at DESC, id DESC
    ");
    $query->execute();
    $users = $query->fetchAll(PDO::FETCH_ASSOC);
    $dbError = false;
} catch (PDOException $error) {
    error_log($error->getMessage());
    $users = [];
    $dbError = true;
}

$message = $_SESSION["user_message"] ?? "";
$messageType = $_SESSION["user_message_type"] ?? "success";

unset($_SESSION["user_message"], $_SESSION["user_message_type"]);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-pages.css">
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

                <a href="produk.php" class="menu-item">
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

                <a href="user.php" class="menu-item active">
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

        <!-- KONTEN UTAMA -->
        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-menu-btn" id="menuButton" type="button">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div>
                        <span class="breadcrumb">Manajemen</span>
                        <h1>User</h1>
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

                    <?php if ($dbError): ?>
                        <p role="alert">
                            Gagal mengambil data user. Periksa koneksi database.
                        </p>
                    <?php else: ?>

                        <div class="page-toolbar">
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input
                                    type="text"
                                    id="searchUser"
                                    placeholder="Cari nama, email, atau role..."
                                    aria-label="Cari user">
                            </div>

                            <button class="btn btn-primary" type="button"
                                onclick="openAddUserModal()">
                                <i class="fa-solid fa-plus"></i>
                                Tambah Admin
                            </button>
                        </div>

                        <div class="table-wrapper">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>NAMA</th>
                                        <th>EMAIL</th>
                                        <th>ROLE</th>
                                        <th>STATUS</th>
                                        <th>AKSI</th>
                                    </tr>
                                </thead>

                                <tbody id="userTableBody">
                                    <?php if (empty($users)): ?>
                                        <tr class="empty-row">
                                            <td colspan="5" style="text-align:center;">
                                                Belum ada user terdaftar.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?= e($user["name"]) ?></td>
                                                <td><?= e($user["email"]) ?></td>

                                                <td>
                                                    <?= $user["role"] === "admin"
                                                        ? "Admin"
                                                        : "Customer" ?>
                                                </td>

                                                <td>
                                                    <span class="status <?= $user["status"] === "active"
                                                                            ? "active"
                                                                            : "inactive" ?>">
                                                        <?= $user["status"] === "active"
                                                            ? "Aktif"
                                                            : "Nonaktif" ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <div class="action-buttons">
                                                        <!-- EDIT -->
                                                        <button
                                                            type="button"
                                                            class="action-btn action-edit"
                                                            title="Edit user"
                                                            data-id="<?= (int)$user["id"] ?>"
                                                            data-name="<?= e($user["name"]) ?>"
                                                            data-email="<?= e($user["email"]) ?>"
                                                            data-phone="<?= e($user["phone"]) ?>"
                                                            data-role="<?= e($user["role"]) ?>"
                                                            data-status="<?= e($user["status"]) ?>"
                                                            onclick="editUser(this)">
                                                            <i class="fa-solid fa-pen"></i>
                                                        </button>

                                                        <!-- HAPUS: sembunyikan untuk akun yang sedang login -->
                                                        <?php if (
                                                            $currentUserId > 0 &&
                                                            (int)$user["id"] !== $currentUserId
                                                        ): ?>
                                                            <form
                                                                method="POST"
                                                                action="../process/user_action.php"
                                                                class="delete-user-form">

                                                                <input type="hidden"
                                                                    name="csrf_token"
                                                                    value="<?= e($_SESSION["csrf_token"]) ?>">

                                                                <input type="hidden"
                                                                    name="action"
                                                                    value="delete">

                                                                <input type="hidden"
                                                                    name="id"
                                                                    value="<?= (int)$user["id"] ?>">

                                                                <button
                                                                    type="submit"
                                                                    class="action-btn action-delete"
                                                                    title="Hapus user">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL TAMBAH / EDIT USER -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="userModalTitle">Tambah Admin</h2>

                <button type="button" class="modal-close"
                    onclick="closeModal('userModal')"
                    aria-label="Tutup">&times;</button>
            </div>

            <form method="POST" action="../process/user_action.php"
                id="userForm">

                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION["csrf_token"]) ?>">

                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="userId">

                <div class="form-group">
                    <label for="userName">Nama Lengkap</label>
                    <input type="text" name="name" id="userName"
                        placeholder="Masukkan nama lengkap"
                        maxlength="100" required>
                </div>

                <div class="form-group">
                    <label for="userEmail">Email</label>
                    <input type="email" name="email" id="userEmail"
                        placeholder="Masukkan email"
                        maxlength="100" required>
                </div>

                <div class="form-group">
                    <label for="userPhone">Nomor Telepon</label>
                    <input type="text" name="phone" id="userPhone"
                        placeholder="Masukkan nomor telepon"
                        maxlength="20">
                </div>

                <div class="form-group">
                    <label for="userPassword">Password</label>
                    <input type="password" name="password" id="userPassword"
                        placeholder="Masukkan password"
                        minlength="8"
                        autocomplete="new-password">

                    <small id="passwordHint">
                        Password minimal 8 karakter.
                    </small>
                </div>

                <div class="form-group">
                    <label for="userRole">Role</label>
                    <select id="userRole" disabled>
                        <option value="admin">Admin</option>
                        <option value="customer">Customer</option>
                    </select>
                    <small>
                        Akun baru dari menu ini otomatis menjadi Admin.
                        Role akun yang sudah ada tidak diubah dari form ini.
                    </small>
                </div>

                <div class="form-group" id="statusGroup" hidden>
                    <label for="userStatus">Status</label>
                    <select name="status" id="userStatus">
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline"
                        onclick="closeModal('userModal')">
                        Batal
                    </button>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i>
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/admin-pages.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function openAddUserModal() {
            const form = document.getElementById("userForm");
            form.reset();

            document.getElementById("formAction").value = "add";
            document.getElementById("userId").value = "";
            document.getElementById("userModalTitle").textContent = "Tambah Admin";

            document.getElementById("userPassword").required = true;
            document.getElementById("userPassword").minLength = 8;
            document.getElementById("userPassword").placeholder =
                "Masukkan password admin";

            document.getElementById("passwordHint").textContent =
                "Password minimal 8 karakter.";

            document.getElementById("userRole").value = "admin";
            document.getElementById("statusGroup").hidden = true;

            openModal("userModal");
        }

        function editUser(button) {
            const form = document.getElementById("userForm");
            form.reset();

            document.getElementById("formAction").value = "edit";
            document.getElementById("userId").value = button.dataset.id;
            document.getElementById("userName").value = button.dataset.name;
            document.getElementById("userEmail").value = button.dataset.email;
            document.getElementById("userPhone").value = button.dataset.phone || "";

            document.getElementById("userRole").value = button.dataset.role;
            document.getElementById("userModalTitle").textContent = "Edit User";

            document.getElementById("userPassword").required = false;
            document.getElementById("userPassword").value = "";
            document.getElementById("userPassword").minLength = 0;
            document.getElementById("userPassword").placeholder =
                "Kosongkan jika tidak diganti";

            document.getElementById("passwordHint").textContent =
                "Isi hanya jika ingin mengganti password.";

            document.getElementById("userStatus").value = button.dataset.status;
            document.getElementById("statusGroup").hidden = false;

            openModal("userModal");
        }

        document.getElementById("searchUser")?.addEventListener("input", function() {
            const keyword = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#userTableBody tr:not(.empty-row)");
            let visibleCount = 0;

            rows.forEach(function(row) {
                const match = row.textContent.toLowerCase().includes(keyword);
                row.style.display = match ? "" : "none";

                if (match) visibleCount++;
            });

            const emptyRow = document.querySelector("#userTableBody .empty-row");

            if (emptyRow) {
                emptyRow.style.display = visibleCount === 0 ? "" : "none";
                const cell = emptyRow.querySelector("td");

                if (cell) {
                    cell.textContent = keyword ?
                        "User tidak ditemukan." :
                        "Belum ada user terdaftar.";
                }
            }
        });

        document.querySelectorAll(".delete-user-form").forEach(function(form) {
            form.addEventListener("submit", async function(event) {
                event.preventDefault();

                const result = await Swal.fire({
                    icon: "warning",
                    title: "Hapus akun ini?",
                    text: "Akun yang dihapus tidak dapat digunakan lagi untuk login.",
                    showCancelButton: true,
                    confirmButtonText: "Ya, hapus",
                    cancelButtonText: "Batal",
                    confirmButtonColor: "#d9534f",
                    cancelButtonColor: "#999",
                    reverseButtons: true
                });

                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        <?php if ($message !== ""): ?>
            Swal.fire({
                icon: <?= json_encode(
                            $messageType === "success" ? "success" : "error"
                        ) ?>,
                title: <?= json_encode(
                            $messageType === "success" ? "Berhasil!" : "Gagal!"
                        ) ?>,
                text: <?= json_encode(
                            $message,
                            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG |
                                JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                        ) ?>,
                confirmButtonText: "Oke",
                confirmButtonColor: "#e88a9b"
            });
        <?php endif; ?>
    </script>
</body>

</html>