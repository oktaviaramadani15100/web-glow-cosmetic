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

function rupiah($angka)
{
    return "Rp " . number_format((float)$angka, 0, ",", ".");
}

$bulan = isset($_GET["bulan"]) ? (int)$_GET["bulan"] : (int)date("m");
$tahun = isset($_GET["tahun"]) ? (int)$_GET["tahun"] : (int)date("Y");

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date("m");
}

if ($tahun < 2020 || $tahun > 2100) {
    $tahun = (int)date("Y");
}

$tanggalMulai = sprintf("%04d-%02d-01", $tahun, $bulan);
$tanggalAkhir = date(
    "Y-m-d",
    strtotime($tanggalMulai . " +1 month")
);
$exportType = $_GET["export"] ?? "";

$namaBulan = [
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

$totalPenjualan = 0;
$totalTransaksi = 0;
$totalStok = 0;
$labelGrafik = [];
$dataGrafik = [];
$laporanHarian = [];
$pesanError = "";

try {

    $query = $conn->prepare("
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN payment_status = 'paid'
                         AND order_status <> 'cancelled'
                        THEN total_amount
                        ELSE 0
                    END
                ), 0
            ) AS total_penjualan,
            SUM(
                CASE
                    WHEN payment_status = 'paid'
                     AND order_status <> 'cancelled'
                    THEN 1
                    ELSE 0
                END
            ) AS total_transaksi
        FROM orders
        WHERE order_date >= :mulai
          AND order_date < :akhir
    ");

    $query->execute([
        "mulai" => $tanggalMulai,
        "akhir" => $tanggalAkhir
    ]);

    $ringkasan = $query->fetch(PDO::FETCH_ASSOC);

    $totalPenjualan = $ringkasan["total_penjualan"] ?? 0;
    $totalTransaksi = $ringkasan["total_transaksi"] ?? 0;

    $queryStok = $conn->query("
        SELECT COALESCE(SUM(quantity), 0) AS total_stok
        FROM stock
    ");

    $dataStok = $queryStok->fetch(PDO::FETCH_ASSOC);
    $totalStok = $dataStok["total_stok"] ?? 0;

    $queryGrafik = $conn->prepare("
        SELECT
            DAY(order_date) AS tanggal,
            COALESCE(SUM(total_amount), 0) AS penjualan,
            COUNT(*) AS jumlah_transaksi
        FROM orders
        WHERE payment_status = 'paid'
          AND order_status <> 'cancelled'
          AND order_date >= :mulai
          AND order_date < :akhir
        GROUP BY DAY(order_date)
        ORDER BY DAY(order_date)
    ");

    $queryGrafik->execute([
        "mulai" => $tanggalMulai,
        "akhir" => $tanggalAkhir
    ]);

    $hasilGrafik = $queryGrafik->fetchAll(PDO::FETCH_ASSOC);

    $jumlahHari = (int)date(
        "t",
        strtotime($tanggalMulai)
    );

    $penjualanHarian = [];
    $transaksiHarian = [];

    foreach ($hasilGrafik as $baris) {
        $penjualanHarian[(int)$baris["tanggal"]] =
            (float)$baris["penjualan"];
        $transaksiHarian[(int)$baris["tanggal"]] =
            (int)$baris["jumlah_transaksi"];
    }

    for ($hari = 1; $hari <= $jumlahHari; $hari++) {
        $labelGrafik[] = (string)$hari;
        $dataGrafik[] = $penjualanHarian[$hari] ?? 0;
        $laporanHarian[] = [
            "tanggal" => sprintf("%04d-%02d-%02d", $tahun, $bulan, $hari),
            "jumlah_transaksi" => $transaksiHarian[$hari] ?? 0,
            "penjualan" => $penjualanHarian[$hari] ?? 0
        ];
    }
} catch (PDOException $e) {
    $pesanError = "Data laporan gagal dimuat. Periksa koneksi database.";
}

if (in_array($exportType, ["excel", "pdf"], true)) {
    if ($pesanError !== "") {
        http_response_code(500);
        exit($pesanError);
    }

    $namaFile = sprintf("laporan-penjualan-%04d-%02d", $tahun, $bulan);

    if ($exportType === "excel") {
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"" . $namaFile . ".csv\"");

        $output = fopen("php://output", "w");
        if ($output === false) {
            http_response_code(500);
            exit("File laporan Excel tidak dapat dibuat.");
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ["Laporan Penjualan", $namaBulan[$bulan] . " " . $tahun]);
        fputcsv($output, ["Total penjualan dibayar", (float)$totalPenjualan]);
        fputcsv($output, ["Jumlah transaksi", (int)$totalTransaksi]);
        fputcsv($output, ["Total stok saat ini", (int)$totalStok]);
        fputcsv($output, []);
        fputcsv($output, ["Tanggal", "Jumlah transaksi dibayar", "Penjualan"]);

        foreach ($laporanHarian as $baris) {
            fputcsv($output, [
                $baris["tanggal"],
                $baris["jumlah_transaksi"],
                $baris["penjualan"]
            ]);
        }

        fclose($output);
        exit;
    }

    $escapePdfText = static function (string $text): string {
        $text = preg_replace('/[^\x20-\x7E]/', " ", $text) ?? "";
        return str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $text);
    };

    $pdfCommands = [];
    $addPdfText = static function (string $text, int $x, int $y, int $size = 10) use (
        &$pdfCommands,
        $escapePdfText
    ): void {
        $pdfCommands[] = "BT /F1 " . $size . " Tf "
            . $x . " " . $y . " Td ("
            . $escapePdfText($text)
            . ") Tj ET";
    };

    $addPdfText("Laporan Penjualan", 40, 800, 18);
    $addPdfText($namaBulan[$bulan] . " " . $tahun, 40, 780, 11);
    $addPdfText("Total penjualan dibayar: " . rupiah($totalPenjualan), 40, 752);
    $addPdfText("Jumlah transaksi dibayar: " . number_format((int)$totalTransaksi, 0, ",", "."), 40, 736);
    $addPdfText("Total stok saat ini: " . number_format((int)$totalStok, 0, ",", "."), 40, 720);
    $addPdfText("Tanggal", 40, 690, 9);
    $addPdfText("Transaksi dibayar", 180, 690, 9);
    $addPdfText("Penjualan", 350, 690, 9);
    $pdfCommands[] = "40 682 m 555 682 l S";

    $rowY = 665;
    foreach ($laporanHarian as $baris) {
        $addPdfText(
            date("d-m-Y", strtotime($baris["tanggal"])),
            40,
            $rowY,
            9
        );
        $addPdfText((string)$baris["jumlah_transaksi"], 180, $rowY, 9);
        $addPdfText(rupiah($baris["penjualan"]), 350, $rowY, 9);
        $rowY -= 15;
    }

    $pdfContent = "0.2 w\n" . implode("\n", $pdfCommands) . "\n";
    $pdfObjects = [
        "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj",
        "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj",
        "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
            . "/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj",
        "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj",
        "5 0 obj << /Length " . strlen($pdfContent) . " >> stream\n"
            . $pdfContent . "endstream endobj"
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($pdfObjects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($pdfObjects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $index => $offset) {
        if ($index > 0) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
    }
    $pdf .= "trailer << /Size " . (count($pdfObjects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    header("Content-Type: application/pdf");
    header("Content-Disposition: attachment; filename=\"" . $namaFile . ".pdf\"");
    header("Content-Length: " . strlen($pdf));
    echo $pdf;
    exit;
}

// Tanggal saat ini
$tanggalSekarang = date("d-m-Y");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan - Glow.Cosmetic</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../css/admin-pages.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .report-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .report-filter select {
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .report-filter form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .report-export {
            position: relative;
        }

        .report-export summary {
            list-style: none;
            cursor: pointer;
        }

        .report-export summary::-webkit-details-marker {
            display: none;
        }

        .report-export-menu {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            z-index: 20;
            min-width: 170px;
            padding: 6px;
            border: 1px solid #eee;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(50, 20, 30, 0.14);
        }

        .report-export-menu a {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 10px;
            border-radius: 5px;
            color: #555;
            font-size: 12px;
            text-decoration: none;
        }

        .report-export-menu a:hover {
            background: #fff2f5;
            color: #c83e67;
        }

        .report-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-top: 22px;
        }

        .report-box {
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
        }

        .report-box span {
            display: block;
            color: #777;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .report-box strong {
            font-size: 22px;
            overflow-wrap: anywhere;
        }

        .report-chart {
            height: 280px;
            padding: 10px 0;
        }

        .report-period {
            color: #777;
            font-size: 13px;
            margin-top: 6px;
        }

        @media (max-width: 650px) {
            .report-summary {
                grid-template-columns: 1fr;
            }

            .report-box strong {
                font-size: 20px;
            }
        }

        @media print {
            body {
                background: white !important;
            }

            .sidebar,
            .top-header,
            .no-print {
                display: none !important;
            }

            .admin-layout,
            .main-content,
            .page-content,
            .management-card {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }

            .management-card {
                margin-bottom: 25px !important;
            }

            .report-summary {
                grid-template-columns: repeat(3, 1fr) !important;
            }

            .report-box {
                break-inside: avoid;
            }

            .report-chart {
                height: 280px !important;
            }

            canvas {
                max-width: 100% !important;
            }

            @page {
                size: A4;
                margin: 15mm;
            }
        }
    </style>
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

                <a href="user.php" class="menu-item">
                    <i class="fas fa-users"></i>

                    <span>
                        Users
                    </span>
                </a>

                <a href="laporan.php" class="menu-item active">
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

                    <button class="mobile-menu-btn no-print" id="menuButton">
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div>
                        <span class="breadcrumb">Manajemen</span>
                        <h1>Laporan</h1>
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

                <?php if ($pesanError !== ""): ?>
                    <div class="management-card">
                        <?= htmlspecialchars($pesanError) ?>
                    </div>
                <?php endif; ?>

                <!-- Ringkasan laporan -->
                <div class="management-card">

                    <div class="page-toolbar">

                        <div>
                            <strong style="font-size:13px;">
                                Laporan Penjualan
                            </strong>

                            <p class="report-period">
                                Periode:
                                <?= htmlspecialchars($namaBulan[$bulan]) ?>
                                <?= $tahun ?>
                            </p>
                        </div>

                        <div class="report-filter no-print">

                            <form method="GET" action="laporan.php">

                                <select name="bulan" aria-label="Pilih bulan laporan">
                                    <?php foreach ($namaBulan as $nomor => $nama): ?>
                                        <option value="<?= $nomor ?>"
                                            <?= $bulan === $nomor ? "selected" : "" ?>>
                                            <?= htmlspecialchars($nama) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="tahun" aria-label="Pilih tahun laporan">
                                    <?php for ($y = (int)date("Y"); $y >= 2020; $y--): ?>
                                        <option value="<?= $y ?>"
                                            <?= $tahun === $y ? "selected" : "" ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-filter"></i>
                                    Tampilkan
                                </button>

                            </form>

                            <details class="report-export">
                                <summary class="btn btn-primary">
                                    <i class="fa-solid fa-download"></i>
                                    Cetak / Unduh
                                    <i class="fa-solid fa-chevron-down"></i>
                                </summary>

                                <div class="report-export-menu">
                                    <a href="laporan.php?<?= htmlspecialchars(http_build_query([
                                                                "bulan" => $bulan,
                                                                "tahun" => $tahun,
                                                                "export" => "excel"
                                                            ]), ENT_QUOTES, "UTF-8") ?>">
                                        <i class="fa-solid fa-file-excel"></i>
                                        Excel (CSV)
                                    </a>
                                    <a href="laporan.php?<?= htmlspecialchars(http_build_query([
                                                                "bulan" => $bulan,
                                                                "tahun" => $tahun,
                                                                "export" => "pdf"
                                                            ]), ENT_QUOTES, "UTF-8") ?>">
                                        <i class="fa-solid fa-file-pdf"></i>
                                        PDF
                                    </a>
                                </div>
                            </details>

                        </div>

                    </div>

                    <div class="report-summary">

                        <div class="report-box">
                            <span>Penjualan Dibayar</span>
                            <strong><?= rupiah($totalPenjualan) ?></strong>
                        </div>

                        <div class="report-box">
                            <span>Jumlah Transaksi</span>
                            <strong>
                                <?= number_format((int)$totalTransaksi, 0, ",", ".") ?>
                            </strong>
                        </div>

                        <div class="report-box">
                            <span>Stok Tersisa</span>
                            <strong>
                                <?= number_format((int)$totalStok, 0, ",", ".") ?>
                            </strong>
                        </div>

                    </div>

                </div>

                <br>

                <!-- Grafik penjualan -->
                <div class="management-card">

                    <div class="page-toolbar">
                        <div>
                            <strong style="font-size:13px;">
                                Grafik Penjualan
                            </strong>
                        </div>
                    </div>

                    <p class="report-period">
                        Grafik menunjukkan penjualan harian untuk transaksi
                        yang sudah dibayar.
                    </p>

                    <div class="report-chart">
                        <canvas id="reportChart"></canvas>
                    </div>

                </div>

            </div>

        </main>

    </div>

    <script>
        const labelGrafik = <?= json_encode($labelGrafik) ?>;
        const dataGrafik = <?= json_encode($dataGrafik) ?>;

        const ctx = document.getElementById("reportChart");

        new Chart(ctx, {
            type: "bar",

            data: {
                labels: labelGrafik.map(tanggal => tanggal),
                datasets: [{
                    label: "Penjualan (Rp)",
                    data: dataGrafik,
                    borderWidth: 0,
                    borderRadius: 5
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: false
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return "Penjualan: Rp " +
                                    Number(context.raw).toLocaleString("id-ID");
                            }
                        }
                    }
                },

                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return "Rp " +
                                    Number(value).toLocaleString("id-ID");
                            }
                        }
                    },

                    x: {
                        title: {
                            display: true,
                            text: "Tanggal"
                        }
                    }
                }
            }
        });
    </script>

    <script src="../js/admin-pages.js"></script>

</body>

</html>