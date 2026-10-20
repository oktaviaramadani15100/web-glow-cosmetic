<?php
session_start();
require_once "../config/database.php";

if (($_SESSION["user_role"] ?? $_SESSION["role"] ?? "") !== "admin") {
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

$adminId = (int) ($_SESSION["user_id"] ?? $_SESSION["id"] ?? 0);

if ($adminId <= 0) {
    exit("Session ID admin tidak ditemukan. Silakan login ulang.");
}

$alert = "";
$alertType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    try {
        if ($aksi === "edit_profil") {
            $name  = trim($_POST["name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $phone = trim($_POST["phone"] ?? "");

            if ($name === "" || $email === "") {
                throw new Exception("Nama dan email wajib diisi.");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Format email tidak valid.");
            }

            $stmt = $conn->prepare(
                "SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1"
            );
            $stmt->execute([
                ":email" => $email,
                ":id" => $adminId
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Email tersebut sudah digunakan akun lain.");
            }

            $stmt = $conn->prepare(
                "UPDATE users
                 SET name = :name,
                     email = :email,
                     phone = :phone,
                     updated_at = NOW()
                 WHERE id = :id AND role = 'admin'"
            );

            $stmt->execute([
                ":name" => $name,
                ":email" => $email,
                ":phone" => $phone,
                ":id" => $adminId
            ]);

            $_SESSION["user_name"] = $name;
            $_SESSION["name"] = $name;
            $_SESSION["user_email"] = $email;

            $_SESSION["profile_flash"] = [
                "type" => "success",
                "message" => "Profil berhasil diperbarui."
            ];
        } elseif ($aksi === "ubah_password") {
            $passwordLama = $_POST["password_lama"] ?? "";
            $passwordBaru = $_POST["password_baru"] ?? "";
            $konfirmasi   = $_POST["konfirmasi_password"] ?? "";

            if ($passwordLama === "" || $passwordBaru === "" || $konfirmasi === "") {
                throw new Exception("Semua kolom password wajib diisi.");
            }

            if (strlen($passwordBaru) < 8) {
                throw new Exception("Password baru minimal 8 karakter.");
            }

            if ($passwordBaru !== $konfirmasi) {
                throw new Exception("Konfirmasi password baru tidak cocok.");
            }

            $stmt = $conn->prepare(
                "SELECT password FROM users
                 WHERE id = :id AND role = 'admin' LIMIT 1"
            );
            $stmt->execute([":id" => $adminId]);
            $akun = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$akun) {
                throw new Exception("Akun admin tidak ditemukan.");
            }

            $passwordTersimpan = $akun["password"];
            $passwordCocok = password_verify($passwordLama, $passwordTersimpan);

            if (!$passwordCocok && $passwordTersimpan === $passwordLama) {
                $passwordCocok = true;
            }

            if (!$passwordCocok) {
                throw new Exception("Password lama yang kamu masukkan salah.");
            }

            $hashPassword = password_hash($passwordBaru, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "UPDATE users
                 SET password = :password,
                     updated_at = NOW()
                 WHERE id = :id AND role = 'admin'"
            );

            $stmt->execute([
                ":password" => $hashPassword,
                ":id" => $adminId
            ]);

            $_SESSION["profile_flash"] = [
                "type" => "success",
                "message" => "Password berhasil diubah."
            ];
        } else {
            throw new Exception("Permintaan tidak dikenali.");
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());

        $_SESSION["profile_flash"] = [
            "type" => "error",
            "message" => "Terjadi kesalahan database. Periksa kembali data kamu."
        ];
    } catch (Exception $ex) {
        $_SESSION["profile_flash"] = [
            "type" => "error",
            "message" => $ex->getMessage()
        ];
    }

    header("Location: profile.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, name, email, phone, role, status, created_at, updated_at
     FROM users
     WHERE id = :id AND role = 'admin'
     LIMIT 1"
);
$stmt->execute([":id" => $adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    exit("Data admin tidak ditemukan. Pastikan ID session sesuai dengan tabel users.");
}

if (!empty($_SESSION["profile_flash"])) {
    $alert = $_SESSION["profile_flash"]["message"];
    $alertType = $_SESSION["profile_flash"]["type"];
    unset($_SESSION["profile_flash"]);
}

$namaAdmin = $admin["name"] ?: "Admin";
$emailAdmin = $admin["email"] ?: "-";
$phoneAdmin = $admin["phone"] ?: "-";
$roleAdmin = ucfirst($admin["role"]);
$statusAdmin = strtolower((string) $admin["status"]);
$statusAktif = in_array($statusAdmin, ["active", "aktif"], true);

if (empty($_SESSION["csrf_profile"])) {
    $_SESSION["csrf_profile"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["csrf_profile"];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin - Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/profile.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="admin-layout">

        <aside class="sidebar">
            <div class="sidebar-logo">
                <img src="../img/logo2.png" alt="Glow.Cosmetic">
            </div>

            <nav class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
                </a>
                <a href="produk.php" class="menu-item">
                    <i class="fa-solid fa-box"></i><span>Produk</span>
                </a>
                <a href="kategori.php" class="menu-item">
                    <i class="fa-solid fa-layer-group"></i><span>Kategori</span>
                </a>
                <a href="stok.php" class="menu-item">
                    <i class="fa-solid fa-boxes-stacked"></i><span>Stok</span>
                </a>
                <a href="pesanan.php" class="menu-item">
                    <i class="fa-solid fa-cart-shopping"></i><span>Pesanan</span>
                </a>
                <a href="pembayaran.php" class="menu-item">
                    <i class="fa-solid fa-wallet"></i><span>Pembayaran</span>
                </a>
                <a href="pengiriman.php" class="menu-item">
                    <i class="fa-solid fa-truck"></i><span>Pengiriman</span>
                </a>
                <a href="user.php" class="menu-item">
                    <i class="fa-solid fa-users"></i><span>Users</span>
                </a>
                <a href="laporan.php" class="menu-item">
                    <i class="fa-solid fa-chart-column"></i><span>Laporan</span>
                </a>
                <a href="faq.php" class="menu-item">
                    <i class="fa-solid fa-circle-question"></i><span>FAQ</span>
                </a>
                <a href="blog.php" class="menu-item">
                    <i class="fa-solid fa-book-open"></i><span>Blog</span>
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
                    <div>
                        <span class="breadcrumb">Manajemen</span>
                        <h1>Profil Admin</h1>
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

            <div class="profile-content">

                <section class="profile-header-card">
                    <div class="profile-avatar-large">
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <div class="profile-header-info">
                        <h2><?= e($namaAdmin) ?></h2>
                        <p><?= e($roleAdmin) ?></p>

                        <span class="status-active"
                            style="<?= $statusAktif ? '' : 'color:#c0392b;' ?>">
                            <i class="fa-solid fa-circle"></i>
                            <?= $statusAktif ? 'Akun Aktif' : 'Akun Tidak Aktif' ?>
                        </span>
                    </div>

                    <button class="edit-profile-btn" type="button"
                        onclick="openEditProfile()">
                        <i class="fa-solid fa-pen"></i> Edit Profil
                    </button>
                </section>

                <section class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <h2>Informasi Akun</h2>
                            <p>Informasi dasar akun administrator.</p>
                        </div>
                    </div>

                    <div class="profile-grid">
                        <div class="profile-field">
                            <span>Nama Lengkap</span>
                            <strong id="profileName"><?= e($namaAdmin) ?></strong>
                        </div>

                        <div class="profile-field">
                            <span>Username / ID</span>
                            <strong><?= (int) $admin["id"] ?></strong>
                        </div>

                        <div class="profile-field">
                            <span>Email</span>
                            <strong id="profileEmail"><?= e($emailAdmin) ?></strong>
                        </div>

                        <div class="profile-field">
                            <span>Nomor Handphone</span>
                            <strong id="profilePhone"><?= e($phoneAdmin) ?></strong>
                        </div>

                        <div class="profile-field">
                            <span>Role</span>
                            <strong><?= e($roleAdmin) ?></strong>
                        </div>

                        <div class="profile-field">
                            <span>Status Akun</span>
                            <strong class="field-status">
                                <?= $statusAktif ? 'Aktif' : e(ucfirst($statusAdmin ?: 'Tidak diketahui')) ?>
                            </strong>
                        </div>

                        <div class="profile-field">
                            <span>Terdaftar Sejak</span>
                            <strong>
                                <?= !empty($admin["created_at"])
                                    ? e(date("d M Y", strtotime($admin["created_at"])))
                                    : "-" ?>
                            </strong>
                        </div>

                        <div class="profile-field">
                            <span>Terakhir Diperbarui</span>
                            <strong>
                                <?= !empty($admin["updated_at"])
                                    ? e(date("d M Y", strtotime($admin["updated_at"])))
                                    : "-" ?>
                            </strong>
                        </div>
                    </div>
                </section>

                <section class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <h2>Keamanan Akun</h2>
                            <p>Kelola keamanan akun administrator.</p>
                        </div>
                    </div>

                    <div class="security-item">
                        <div class="security-icon">
                            <i class="fa-solid fa-lock"></i>
                        </div>

                        <div class="security-info">
                            <strong>Password</strong>
                            <span>Ubah password secara berkala untuk menjaga keamanan akun.</span>
                        </div>

                        <button class="security-btn" type="button"
                            onclick="openPasswordModal()">
                            Ubah Password
                        </button>
                    </div>
                </section>

                <section class="logout-card">
                    <div>
                        <h3>Keluar dari Akun</h3>
                        <p>Kamu akan keluar dari halaman administrator.</p>
                    </div>

                    <button class="logout-btn" type="button" onclick="logoutAdmin()">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </button>
                </section>

            </div>
        </main>
    </div>

    <!-- MODAL EDIT PROFIL -->
    <div class="modal" id="editProfileModal">
        <div class="modal-content">
            <form method="POST" id="editProfileForm">
                <input type="hidden" name="aksi" value="edit_profil">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="modal-header">
                    <div>
                        <h2>Edit Profil</h2>
                    </div>
                    <button type="button" onclick="closeEditProfile()" aria-label="Tutup">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="form-group">
                    <label for="editName">Nama Lengkap</label>
                    <input type="text" id="editName" name="name"
                        value="<?= e($namaAdmin) ?>" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email"
                        value="<?= e($emailAdmin) ?>" required maxlength="150">
                </div>

                <div class="form-group">
                    <label for="editPhone">Nomor Handphone</label>
                    <input type="text" id="editPhone" name="phone"
                        value="<?= e($admin["phone"] ?? "") ?>" maxlength="25">
                </div>

                <div class="modal-actions">
                    <button type="button" class="cancel-btn"
                        onclick="closeEditProfile()">Batal</button>
                    <button type="submit" class="save-btn">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL UBAH PASSWORD -->
    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <form method="POST" id="passwordForm">
                <input type="hidden" name="aksi" value="ubah_password">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="modal-header">
                    <div>
                        <span>KEAMANAN</span>
                        <h2>Ubah Password</h2>
                    </div>
                    <button type="button" onclick="closePasswordModal()" aria-label="Tutup">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="form-group">
                    <label for="passwordLama">Password Lama</label>
                    <input type="password" id="passwordLama" name="password_lama"
                        placeholder="Masukkan password lama" required autocomplete="current-password">
                </div>

                <div class="form-group">
                    <label for="passwordBaru">Password Baru</label>
                    <input type="password" id="passwordBaru" name="password_baru"
                        placeholder="Minimal 8 karakter" required minlength="8"
                        autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="konfirmasiPassword">Konfirmasi Password</label>
                    <input type="password" id="konfirmasiPassword"
                        name="konfirmasi_password" placeholder="Ulangi password baru"
                        required minlength="8" autocomplete="new-password">
                </div>

                <div class="modal-actions">
                    <button type="button" class="cancel-btn"
                        onclick="closePasswordModal()">Batal</button>
                    <button type="submit" class="save-btn">Simpan Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const editProfileModal = document.getElementById("editProfileModal");
        const passwordModal = document.getElementById("passwordModal");

        function openEditProfile() {
            editProfileModal.classList.add("show");
            editProfileModal.style.display = "flex";
        }

        function closeEditProfile() {
            editProfileModal.classList.remove("show");
            editProfileModal.style.display = "none";
        }

        function openPasswordModal() {
            document.getElementById("passwordForm").reset();
            passwordModal.classList.add("show");
            passwordModal.style.display = "flex";
        }

        function closePasswordModal() {
            passwordModal.classList.remove("show");
            passwordModal.style.display = "none";
        }

        function logoutAdmin() {
            Swal.fire({
                title: "Keluar dari akun?",
                text: "Kamu perlu login kembali untuk masuk ke halaman admin.",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Ya, logout",
                cancelButtonText: "Batal",
                confirmButtonColor: "#d46a91"
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = "../process/logout.php";
                }
            });
        }

        [editProfileModal, passwordModal].forEach(function(modal) {
            modal.addEventListener("click", function(event) {
                if (event.target === modal) {
                    modal.classList.remove("show");
                    modal.style.display = "none";
                }
            });
        });

        document.addEventListener("keydown", function(event) {
            if (event.key === "Escape") {
                closeEditProfile();
                closePasswordModal();
            }
        });

        document.getElementById("passwordForm").addEventListener("submit", function(event) {
            const baru = document.getElementById("passwordBaru").value;
            const konfirmasi = document.getElementById("konfirmasiPassword").value;

            if (baru !== konfirmasi) {
                event.preventDefault();
                Swal.fire("Password tidak cocok", "Konfirmasi password harus sama dengan password baru.", "warning");
            }
        });

        <?php if ($alert !== ""): ?>
            Swal.fire({
                icon: <?= json_encode($alertType === "success" ? "success" : "error") ?>,
                title: <?= json_encode($alertType === "success" ? "Berhasil!" : "Gagal!") ?>,
                text: <?= json_encode($alert) ?>,
                confirmButtonText: "OK",
                confirmButtonColor: "#d46a91"
            });
        <?php endif; ?>
    </script>

</body>

</html>