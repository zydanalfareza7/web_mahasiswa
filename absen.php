<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/koneksi.php';

$pesan = "";
$jenisPesan = "success";
$tampilFormDaftar = false;

// Pastikan folder uploads/absensi ada
$uploadDir = __DIR__ . '/uploads/absensi/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Cek otomatis apakah kolom foto, latitude, longitude sudah ada di tb_absensi
$hasFotoCol = false;
$cekKolom = @mysqli_query($koneksi, "SHOW COLUMNS FROM tb_absensi LIKE 'foto'");
if ($cekKolom && mysqli_num_rows($cekKolom) > 0) {
    $hasFotoCol = true;
} else {
    // Upaya auto-alter jika user belum sempat menjalankan script SQL
    @mysqli_query($koneksi, "ALTER TABLE `tb_absensi` ADD COLUMN `foto` varchar(255) DEFAULT NULL AFTER `status`, ADD COLUMN `latitude` varchar(50) DEFAULT NULL AFTER `foto`, ADD COLUMN `longitude` varchar(50) DEFAULT NULL AFTER `latitude`");
    $cekKolom2 = @mysqli_query($koneksi, "SHOW COLUMNS FROM tb_absensi LIKE 'foto'");
    if ($cekKolom2 && mysqli_num_rows($cekKolom2) > 0) {
        $hasFotoCol = true;
    }
}

$npm = "";
$status = "Hadir";
$latitude = "";
$longitude = "";
$fotoBase64 = "";
$fotoSavedName = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $npm = trim($_POST["npm"] ?? "");
    $status = trim($_POST["status"] ?? "Hadir");
    $aksiForm = $_POST["aksi_form"] ?? "absen";
    $latitude = trim($_POST["latitude"] ?? "");
    $longitude = trim($_POST["longitude"] ?? "");
    $fotoBase64 = trim($_POST["foto_base64"] ?? "");
    $fotoSavedName = trim($_POST["foto_saved_name"] ?? "");

    // Validasi Ketat: Wajib NPM, GPS, dan Foto
    if ($npm === "") {
        $pesan = "NPM wajib diisi untuk melakukan absensi.";
        $jenisPesan = "error";
        if ($aksiForm === "daftar_dan_absen") $tampilFormDaftar = true;
    } elseif ($latitude === "" || $longitude === "" || $latitude === "null" || $longitude === "null") {
        $pesan = "⚠️ <strong>Absensi Ditolak:</strong> Titik koordinat GPS tidak terdeteksi! Anda wajib mengaktifkan GPS dan mengizinkan akses lokasi browser untuk dapat absen.";
        $jenisPesan = "error";
        if ($aksiForm === "daftar_dan_absen") $tampilFormDaftar = true;
    } elseif ($fotoBase64 === "" && ($fotoSavedName === "" || !file_exists($uploadDir . $fotoSavedName))) {
        $pesan = "⚠️ <strong>Absensi Ditolak:</strong> Bukti foto selfie belum diambil! Anda wajib mengambil foto selfie untuk dapat absen.";
        $jenisPesan = "error";
        if ($aksiForm === "daftar_dan_absen") $tampilFormDaftar = true;
    } else {
        // Simpan atau gunakan kembali foto jika ada
        $fotoName = null;
        if ($fotoSavedName !== "" && file_exists($uploadDir . $fotoSavedName)) {
            // Gunakan file foto yang sudah tersimpan sebelumnya
            $fotoName = $fotoSavedName;
        } elseif ($fotoBase64 !== "") {
            if (preg_match('/^data:image\/(jpeg|png|jpg);base64,/', $fotoBase64)) {
                $imgData = substr($fotoBase64, strpos($fotoBase64, ',') + 1);
                $decoded = base64_decode($imgData);
                if ($decoded !== false) {
                    $cleanNpm = preg_replace('/[^a-zA-Z0-9]/', '', $npm);
                    if ($cleanNpm === "") $cleanNpm = "unknown";
                    $fotoName = 'absen_' . $cleanNpm . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.jpg';
                    @file_put_contents($uploadDir . $fotoName, $decoded);
                    $fotoSavedName = $fotoName;
                }
            }
        }

        $cekMhs = mysqli_prepare($koneksi, "SELECT NAMA FROM tb_mhs WHERE NPM = ?");
        mysqli_stmt_bind_param($cekMhs, "s", $npm);
        mysqli_stmt_execute($cekMhs);
        $resultMhs = mysqli_stmt_get_result($cekMhs);

        if ($rowMhs = mysqli_fetch_assoc($resultMhs)) {
            $namaMhs = $rowMhs['NAMA'];
            mysqli_stmt_close($cekMhs);

            $tanggalHariIni = date('Y-m-d');
            $cekAbsen = mysqli_prepare($koneksi, "SELECT id FROM tb_absensi WHERE npm = ? AND tanggal = ?");
            mysqli_stmt_bind_param($cekAbsen, "ss", $npm, $tanggalHariIni);
            mysqli_stmt_execute($cekAbsen);
            mysqli_stmt_store_result($cekAbsen);

            if (mysqli_stmt_num_rows($cekAbsen) > 0) {
                $pesan = "Mahasiswa atas nama <strong>" . e($namaMhs) . "</strong> (NPM: " . e($npm) . ") sudah melakukan absensi hari ini.";
                $jenisPesan = "error";
            } else {
                mysqli_stmt_close($cekAbsen);

                $jamSekarang = date('H:i:s');
                if ($hasFotoCol) {
                    $stmtInsert = mysqli_prepare($koneksi, "INSERT INTO tb_absensi (npm, tanggal, jam_masuk, status, foto, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmtInsert, "sssssss", $npm, $tanggalHariIni, $jamSekarang, $status, $fotoName, $latitude, $longitude);
                } else {
                    $stmtInsert = mysqli_prepare($koneksi, "INSERT INTO tb_absensi (npm, tanggal, jam_masuk, status) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmtInsert, "ssss", $npm, $tanggalHariIni, $jamSekarang, $status);
                }
                
                if (mysqli_stmt_execute($stmtInsert)) {
                    $lokasiKet = "<br><small style='opacity:0.85'>📍 Lokasi Terverifikasi: {$latitude}, {$longitude}</small>";
                    $fotoKet = ($fotoName !== null) ? " (Bukti Foto Selfie Tersimpan)" : "";
                    $pesan = "Berhasil absen! Selamat datang, <strong>" . e($namaMhs) . "</strong> pada pukul {$jamSekarang}.{$fotoKet}{$lokasiKet}";
                    $jenisPesan = "success";
                    // Reset input setelah sukses
                    $npm = "";
                    $latitude = "";
                    $longitude = "";
                    $fotoBase64 = "";
                    $fotoSavedName = "";
                } else {
                    $pesan = "Gagal menyimpan data absensi.";
                    $jenisPesan = "error";
                }
                mysqli_stmt_close($stmtInsert);
            }
        } else {
            mysqli_stmt_close($cekMhs);

            if ($aksiForm === "daftar_dan_absen") {
                $nama = trim($_POST["nama"] ?? "");
                $kelas = trim($_POST["kelas"] ?? "");
                $jurusan = trim($_POST["jurusan"] ?? "");

                if ($nama === "" || $kelas === "" || $jurusan === "") {
                    $pesan = "NPM belum terdaftar. Harap lengkapi Nama, Kelas, dan Jurusan untuk mendaftar otomatis.";
                    $jenisPesan = "error";
                    $tampilFormDaftar = true;
                } else {
                    $stmtReg = mysqli_prepare($koneksi, "INSERT INTO tb_mhs (NPM, NAMA, KELAS, JURUSAN) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmtReg, "ssss", $npm, $nama, $kelas, $jurusan);
                    
                    if (mysqli_stmt_execute($stmtReg)) {
                        mysqli_stmt_close($stmtReg);

                        $tanggalHariIni = date('Y-m-d');
                        $jamSekarang = date('H:i:s');
                        
                        if ($hasFotoCol) {
                            $stmtInsert = mysqli_prepare($koneksi, "INSERT INTO tb_absensi (npm, tanggal, jam_masuk, status, foto, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            mysqli_stmt_bind_param($stmtInsert, "sssssss", $npm, $tanggalHariIni, $jamSekarang, $status, $fotoName, $latitude, $longitude);
                        } else {
                            $stmtInsert = mysqli_prepare($koneksi, "INSERT INTO tb_absensi (npm, tanggal, jam_masuk, status) VALUES (?, ?, ?, ?)");
                            mysqli_stmt_bind_param($stmtInsert, "ssss", $npm, $tanggalHariIni, $jamSekarang, $status);
                        }
                        mysqli_stmt_execute($stmtInsert);
                        mysqli_stmt_close($stmtInsert);

                        $lokasiKet = "<br><small style='opacity:0.85'>📍 Lokasi Terverifikasi: {$latitude}, {$longitude}</small>";
                        $fotoKet = ($fotoName !== null) ? " beserta bukti foto selfie" : "";
                        $pesan = "Pendaftaran otomatis berhasil! Absensi kamu (<strong>" . e($nama) . "</strong>) pada pukul {$jamSekarang} telah tercatat{$fotoKet}.{$lokasiKet}";
                        $jenisPesan = "success";
                        $tampilFormDaftar = false;
                        // Reset input setelah sukses
                        $npm = "";
                        $latitude = "";
                        $longitude = "";
                        $fotoBase64 = "";
                        $fotoSavedName = "";
                    } else {
                        $pesan = "Gagal memproses pendaftaran otomatis.";
                        $jenisPesan = "error";
                        $tampilFormDaftar = true;
                    }
                }
            } else {
                $pesan = "NPM <strong>" . e($npm) . "</strong> belum terdaftar di sistem. Silakan lengkapi data diri di bawah ini untuk mendaftar dan langsung absen:";
                $jenisPesan = "error";
                $tampilFormDaftar = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Absensi Mandiri Mahasiswa | StudentHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --blue: #3b82f6;
    --blue-hover: #2563eb;
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e2e8f0;
    --bg: #f8fafc;
    --card-bg: #ffffff;
    --input-bg: #ffffff;
    --green: #10b981;
    --green-hover: #059669;
    --red: #ef4444;
}

[data-theme="dark"] {
    --blue: #3b82f6;
    --blue-hover: #60a5fa;
    --ink: #f8fafc;
    --muted: #94a3b8;
    --line: #334155;
    --bg: #0b1120;
    --card-bg: #1e293b;
    --input-bg: #0f172a;
    --green: #10b981;
    --green-hover: #34d399;
    --red: #f87171;
}

* { box-sizing: border-box; }
body {
    margin: 0;
    background-color: var(--bg);
    background-image: 
        radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.06) 0px, transparent 45%),
        radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.05) 0px, transparent 45%);
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    display: grid;
    place-items: center;
    min-height: 100vh;
    padding: 24px 16px;
    position: relative;
    transition: background-color .3s ease, color .3s ease;
}

.theme-toggle-fixed {
    position: absolute;
    top: 20px;
    right: 20px;
    background: var(--card-bg);
    border: 1px solid var(--line);
    color: var(--muted);
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: all .2s;
}
.theme-toggle-fixed:hover {
    color: var(--ink);
    border-color: var(--blue);
}

.container {
    width: 100%;
    max-width: 520px;
    animation: fadeIn .4s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}
.brand-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    display: grid;
    place-items: center;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
.brand-text {
    font-size: 18px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.4px;
}

.panel {
    background: var(--card-bg);
    border: 1px solid var(--line);
    border-radius: 20px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.06);
    padding: 32px 28px;
    transition: background .3s, border .3s;
}

.eyebrow {
    color: var(--green);
    font-weight: 700;
    font-size: 11px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    margin-bottom: 6px;
}

h1 { margin: 0 0 6px; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
.sub { color: var(--muted); font-size: 13px; margin-bottom: 22px; line-height: 1.5; }

.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; }
.field input, .field select {
    width: 100%;
    border: 1px solid var(--line);
    background: var(--input-bg);
    border-radius: 10px;
    padding: 11px 14px;
    font-size: 13px;
    color: var(--ink);
    font-family: inherit;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.field input:focus, .field select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* ================= SECTION KAMERA & LOKASI GPS ================= */
.camera-card {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 16px;
    margin: 20px 0;
}
.camera-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 8px;
}
.camera-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 6px;
}
.gps-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    padding: 5px 11px;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0.12);
    color: var(--green);
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    user-select: none;
}
.gps-pill.loading {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}
.gps-pill.error {
    background: rgba(239, 68, 68, 0.12);
    color: var(--red);
}
.gps-pill:hover {
    transform: translateY(-1px);
}

.gps-instruction {
    font-size: 11px;
    color: var(--muted);
    margin-bottom: 10px;
    line-height: 1.4;
}
.gps-instruction strong { color: var(--red); }

.camera-preview-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 3;
    max-height: 280px;
    border-radius: 12px;
    overflow: hidden;
    background: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1);
}
#cameraVideo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1); /* Mirror selfie */
}
#photoCanvas {
    display: none;
}
#photoPreviewImg {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.camera-guide-overlay {
    position: absolute;
    inset: 16px;
    border: 2px dashed rgba(255, 255, 255, 0.4);
    border-radius: 50%;
    max-width: 170px;
    max-height: 210px;
    margin: auto;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}
.camera-guide-text {
    background: rgba(15, 23, 42, 0.7);
    color: #f8fafc;
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 6px;
    position: absolute;
    bottom: -22px;
    white-space: nowrap;
}

.camera-controls {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}
.btn-snap {
    flex: 1;
    background: var(--blue);
    color: white;
    border: 0;
    border-radius: 10px;
    padding: 11px 14px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: background-color .2s;
}
.btn-snap:hover { background: var(--blue-hover); }

.btn-retake {
    background: transparent;
    border: 1px solid var(--line);
    color: var(--muted);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all .2s;
}
.btn-retake:hover {
    color: var(--ink);
    border-color: var(--muted);
}

.photo-status-badge {
    margin-top: 10px;
    font-size: 11px;
    color: var(--green);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ================= TOMBOL UTAMA & TOAST ================= */
.btn-primary {
    background: var(--green);
    color: #fff;
    border: 0;
    border-radius: 10px;
    padding: 13px;
    width: 100%;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
    transition: transform .15s ease, background-color .2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-primary:hover { background: var(--green-hover); }
.btn-primary:active { transform: scale(0.985); }

.toast {
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 12.5px;
    font-weight: 600;
    line-height: 1.5;
}
.toast.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.toast.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

[data-theme="dark"] .toast.success {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
    border-color: rgba(16, 185, 129, 0.3);
}
[data-theme="dark"] .toast.error {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    border-color: rgba(239, 68, 68, 0.3);
}

.toast-actions {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid rgba(16, 185, 129, 0.25);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
[data-theme="dark"] .toast-actions {
    border-top-color: rgba(110, 231, 183, 0.2);
}

.btn-success-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #059669;
    color: #ffffff !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    padding: 10px 18px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    transition: all .2s ease;
}
.btn-success-back:hover {
    background: #047857;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(5, 150, 105, 0.4);
}
.btn-success-back:active {
    transform: scale(0.98);
}

.back-wrapper {
    margin-top: 22px;
    display: flex;
    justify-content: center;
}
.btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 20px;
    background: var(--card-bg);
    border: 1px solid var(--line);
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: all .2s ease;
}
.btn-back:hover {
    border-color: var(--blue);
    color: var(--blue);
    background: var(--card-bg);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.15);
}
.btn-back:active {
    transform: scale(0.98);
}

.extra-fields {
    background: var(--bg);
    padding: 16px;
    border-radius: 12px;
    border: 1px dashed var(--blue);
    margin-bottom: 16px;
}
</style>
<script>
if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
}
</script>
</head>
<body>

<button class="theme-toggle-fixed" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Ganti Tema">
  <span id="themeIcon"></span>
  <span id="themeText">Mode Gelap</span>
</button>

<div class="container">
  <div class="brand">
    <div class="brand-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
      </svg>
    </div>
    <span class="brand-text">StudentHub &bull; Absensi</span>
  </div>

  <div class="panel">
    <div class="eyebrow">PORTAL MAHASISWA</div>
    <h1>Form Absensi Harian</h1>
    <div class="sub">Silakan isi identitas, ambil foto selfie, dan pastikan lokasi GPS aktif untuk merekam kehadiran.</div>

    <?php if ($pesan !== ""): ?>
      <div class="toast <?= e($jenisPesan) ?>">
        <div class="toast-body"><?= $pesan ?></div>
        <?php if ($jenisPesan === "success"): ?>
          <div class="toast-actions">
            <a href="login.php" class="btn-success-back">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
              <span>Selesai &amp; Kembali ke Halaman Login</span>
            </a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="formAbsen" onsubmit="return handleFormSubmit(event)">
      <?php if ($tampilFormDaftar): ?>
        <input type="hidden" name="aksi_form" value="daftar_dan_absen">
        <input type="hidden" name="npm" id="npmInput" value="<?= e($npm) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        
        <div class="extra-fields">
          <p style="margin:0 0 12px; font-size:12px; font-weight:700; color:var(--blue);">📝 Lengkapi Data Pendaftaran Otomatis:</p>
          <div style="font-size:12px; margin-bottom:12px; color:var(--muted);">
            NPM: <strong><?= e($npm) ?></strong>
          </div>
          <div class="field" style="margin-bottom:12px;">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" id="namaInput" placeholder="Contoh: Budi Santoso" value="<?= e($_POST['nama'] ?? '') ?>" required autofocus>
          </div>
          <div class="field" style="margin-bottom:12px;">
            <label>Kelas</label>
            <input type="text" name="kelas" placeholder="Contoh: 4IA23" value="<?= e($_POST['kelas'] ?? '') ?>" required>
          </div>
          <div class="field" style="margin-bottom:0;">
            <label>Jurusan</label>
            <input type="text" name="jurusan" placeholder="Contoh: Informatika" value="<?= e($_POST['jurusan'] ?? '') ?>" required>
          </div>
        </div>
      <?php else: ?>
        <input type="hidden" name="aksi_form" value="absen">
        <div class="field">
          <label for="npmInput">NPM Mahasiswa</label>
          <input type="text" id="npmInput" name="npm" placeholder="Contoh: 51423046" value="<?= e($npm) ?>" autofocus required>
        </div>
        <div class="field">
          <label for="statusSelect">Status Kehadiran</label>
          <select id="statusSelect" name="status">
            <option value="Hadir" <?= ($status === 'Hadir') ? 'selected' : '' ?>>Hadir</option>
            <option value="Izin" <?= ($status === 'Izin') ? 'selected' : '' ?>>Izin</option>
            <option value="Sakit" <?= ($status === 'Sakit') ? 'selected' : '' ?>>Sakit</option>
            <option value="Alpa" <?= ($status === 'Alpa') ? 'selected' : '' ?>>Alpa</option>
          </select>
        </div>
      <?php endif; ?>

      <!-- Section Kamera & Watermark GPS -->
      <div class="camera-card">
        <div class="camera-header">
          <div class="camera-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
            <span>Foto Selfie & Titik Koordinat (Wajib)</span>
          </div>
          <div class="gps-pill <?= (!empty($latitude)) ? '' : 'loading' ?>" id="gpsBadge" onclick="initGeolocation(true)" title="Klik untuk refresh / izinkan lokasi GPS">
            <span id="gpsDot"><?= (!empty($latitude)) ? '📍' : '⏳' ?></span>
            <span id="gpsText"><?= (!empty($latitude)) ? 'GPS: ' . e(number_format((float)$latitude, 4, '.', '') . ', ' . number_format((float)$longitude, 4, '.', '')) : 'Mencari GPS (Klik)...' ?></span>
          </div>
        </div>

        <div class="gps-instruction" id="gpsInstruction">
          ⚠️ <strong>Wajib:</strong> Pastikan izin lokasi (GPS) dan kamera diaktifkan agar tombol foto & absensi dapat digunakan.
        </div>

        <div class="camera-preview-wrap">
          <video id="cameraVideo" autoplay playsinline muted style="<?= (!empty($fotoBase64)) ? 'display:none;' : '' ?>"></video>
          <img id="photoPreviewImg" 
               src="<?= (!empty($fotoBase64)) ? e($fotoBase64) : '' ?>" 
               alt="Pratinjau Foto Absensi" 
               style="<?= (!empty($fotoBase64)) ? 'display:block;' : 'display:none;' ?>">
          <canvas id="photoCanvas"></canvas>
          <div class="camera-guide-overlay" id="cameraGuide" style="<?= (!empty($fotoBase64)) ? 'display:none;' : '' ?>">
            <span class="camera-guide-text">Posisikan Wajah di Sini</span>
          </div>
        </div>

        <div class="camera-controls">
          <button type="button" class="btn-snap" id="btnSnap" onclick="takeSnapshot()" style="<?= (!empty($fotoBase64)) ? 'display:none;' : '' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <circle cx="12" cy="12" r="10"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <span>Ambil Foto Selfie</span>
          </button>
          <button type="button" class="btn-retake" id="btnRetake" onclick="retakeSnapshot()" style="<?= (!empty($fotoBase64)) ? 'display:inline-flex;' : 'display:none;' ?>">
            <span>🔄 Ganti / Ambil Ulang Foto</span>
          </button>
        </div>

        <div class="photo-status-badge" id="photoStatusBadge" style="<?= (!empty($fotoBase64)) ? 'display:flex;' : 'display:none;' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          <span id="statusBadgeText"><?= (!empty($fotoBase64)) ? 'Foto selfie & GPS tersimpan dan siap dikirim!' : 'Foto selfie & GPS berhasil diambil!' ?></span>
        </div>
      </div>

      <!-- Hidden Inputs untuk Foto & GPS -->
      <input type="hidden" name="foto_base64" id="fotoBase64" value="<?= e($fotoBase64) ?>">
      <input type="hidden" name="foto_saved_name" id="fotoSavedName" value="<?= e($fotoSavedName) ?>">
      <input type="hidden" name="latitude" id="latitudeInput" value="<?= e($latitude) ?>">
      <input type="hidden" name="longitude" id="longitudeInput" value="<?= e($longitude) ?>">

      <div style="margin-top: 24px;">
        <button type="submit" class="btn-primary" id="btnSubmitAbsen">
          <span><?= ($tampilFormDaftar) ? 'Daftar & Kirim Absensi Sekarang' : 'Kirim Absensi Sekarang' ?></span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
            <polyline points="12 5 19 12 12 19"/>
          </svg>
        </button>
      </div>
    </form>
  </div>

  <div class="back-wrapper">
    <a href="login.php" class="btn-back">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      <span>Kembali ke Halaman Login</span>
    </a>
  </div>
</div>

<script>
let videoStream = null;
let userLatitude = <?= (!empty($latitude)) ? json_encode((float)$latitude) : 'null' ?>;
let userLongitude = <?= (!empty($longitude)) ? json_encode((float)$longitude) : 'null' ?>;
let isPhotoTaken = <?= (!empty($fotoBase64) || !empty($fotoSavedName)) ? 'true' : 'false' ?>;

// Inisialisasi Geolocation (GPS) Ketat
function initGeolocation(isManualRetry = false) {
    const gpsBadge = document.getElementById('gpsBadge');
    const gpsText = document.getElementById('gpsText');
    const gpsDot = document.getElementById('gpsDot');
    const gpsInstruction = document.getElementById('gpsInstruction');

    if (!navigator.geolocation) {
        gpsBadge.className = 'gps-pill error';
        gpsDot.textContent = '❌';
        gpsText.textContent = 'Perangkat Tidak Mendukung GPS';
        gpsInstruction.innerHTML = '❌ <strong>Perangkat tidak memiliki sensor/fitur lokasi. Absensi tidak dapat dilakukan.</strong>';
        return;
    }

    gpsBadge.className = 'gps-pill loading';
    gpsDot.textContent = '⏳';
    gpsText.textContent = 'Mencari Titik GPS...';

    // Opsi pertama: Coba High Accuracy (GPS Satelit di HP)
    navigator.geolocation.getCurrentPosition(
        (position) => {
            onGpsSuccess(position);
        },
        (error) => {
            // Jika high accuracy gagal/timeout, coba fallback mode normal
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    onGpsSuccess(position);
                },
                (err2) => {
                    onGpsError(err2);
                },
                { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
            );
        },
        { enableHighAccuracy: true, timeout: 7000, maximumAge: 0 }
    );
}

function onGpsSuccess(position) {
    userLatitude = position.coords.latitude;
    userLongitude = position.coords.longitude;
    document.getElementById('latitudeInput').value = userLatitude;
    document.getElementById('longitudeInput').value = userLongitude;

    const gpsBadge = document.getElementById('gpsBadge');
    const gpsText = document.getElementById('gpsText');
    const gpsDot = document.getElementById('gpsDot');
    const gpsInstruction = document.getElementById('gpsInstruction');

    gpsBadge.className = 'gps-pill';
    gpsDot.textContent = '📍';
    gpsText.textContent = `GPS Terkunci: ${userLatitude.toFixed(4)}, ${userLongitude.toFixed(4)}`;
    gpsInstruction.innerHTML = '✔ <strong style="color:var(--green)">GPS Terhubung:</strong> Titik koordinat Anda siap disertakan pada foto selfie.';
}

function onGpsError(error) {
    // Hanya set error jika belum ada koordinat tersimpan
    if (!document.getElementById('latitudeInput').value) {
        userLatitude = null;
        userLongitude = null;
        const gpsBadge = document.getElementById('gpsBadge');
        const gpsText = document.getElementById('gpsText');
        const gpsDot = document.getElementById('gpsDot');
        const gpsInstruction = document.getElementById('gpsInstruction');

        gpsBadge.className = 'gps-pill error';
        gpsDot.textContent = '⚠️';
        if (error.code === 1) {
            gpsText.textContent = 'Izin GPS Ditolak (Klik untuk retry)';
            gpsInstruction.innerHTML = '⚠️ <strong>Izin Lokasi Ditolak:</strong> Anda menolak akses lokasi di browser. Klik ikon gembok/pengaturan di samping URL browser untuk mengizinkan lokasi, lalu klik tombol GPS ini lagi.';
        } else {
            gpsText.textContent = 'GPS Belum Terkunci (Klik untuk retry)';
            gpsInstruction.innerHTML = '⚠️ <strong>GPS Belum Terkunci:</strong> Pastikan fitur Lokasi di perangkat HP/laptop Anda telah diaktifkan, lalu klik tombol di atas.';
        }
    }
}

// Inisialisasi Kamera Live
async function initCamera() {
    // Jika foto sudah ada sebelumnya, jangan nyalakan kamera dulu sampai user klik 'Ambil Ulang'
    if (isPhotoTaken) return;

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 }
            },
            audio: false
        });
        videoStream = stream;
        const video = document.getElementById('cameraVideo');
        video.srcObject = stream;
    } catch (err) {
        console.warn("Gagal membuka kamera langsung:", err);
        const guide = document.getElementById('cameraGuide');
        if (guide) {
            guide.innerHTML = `<span class="camera-guide-text" style="background:#ef4444;bottom:auto;">Izinkan akses kamera di browser</span>`;
        }
    }
}

// Mengambil jepretan foto & membakar Watermark Koordinat + Timestamp
function takeSnapshot() {
    // 1. CEK GPS WAJIB AKTIF
    const currentLat = userLatitude || parseFloat(document.getElementById('latitudeInput').value);
    const currentLng = userLongitude || parseFloat(document.getElementById('longitudeInput').value);

    if (!currentLat || !currentLng || isNaN(currentLat) || isNaN(currentLng)) {
        alert("⚠️ GPS BELUM AKTIF / BELUM DIIZINKAN!\n\nUntuk dapat mengambil foto dan melakukan absensi, Anda WAJIB mengaktifkan GPS dan mengizinkan akses lokasi pada browser.\n\nSilakan izinkan lokasi di browser Anda, lalu klik tombol 'Mencari GPS' di bagian atas.");
        initGeolocation(true);
        return;
    }

    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('photoCanvas');
    const previewImg = document.getElementById('photoPreviewImg');
    const guide = document.getElementById('cameraGuide');
    const btnRetake = document.getElementById('btnRetake');
    const btnSnap = document.getElementById('btnSnap');
    const statusBadge = document.getElementById('photoStatusBadge');
    const statusText = document.getElementById('statusBadgeText');

    if (!video.videoWidth) {
        alert("📷 Kamera belum siap atau belum diizinkan. Silakan izinkan akses kamera di browser Anda!");
        initCamera();
        return;
    }

    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');

    // Gambar frame video dengan efek cermin agar sesuai tampilan selfie
    ctx.save();
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    ctx.restore();

    // Gambar banner watermark di bagian bawah foto
    const bannerHeight = Math.max(72, Math.floor(canvas.height * 0.17));
    ctx.fillStyle = 'rgba(15, 23, 42, 0.78)';
    ctx.fillRect(0, canvas.height - bannerHeight, canvas.width, bannerHeight);

    // Garis aksen hijau emerald di atas banner
    ctx.fillStyle = '#10b981';
    ctx.fillRect(0, canvas.height - bannerHeight, canvas.width, 3);

    // Teks Watermark
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 15px -apple-system, sans-serif';
    ctx.textBaseline = 'top';

    const coordString = `📍 GPS: ${currentLat.toFixed(6)}, ${currentLng.toFixed(6)}`;
    ctx.fillText(coordString, 16, canvas.height - bannerHeight + 12);

    // Timestamp & Identitas (Nama jika ada, atau NPM)
    ctx.font = '12px -apple-system, sans-serif';
    ctx.fillStyle = '#cbd5e1';
    const now = new Date();
    const dateFormatted = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    const timeFormatted = now.toLocaleTimeString('id-ID', { hour12: false }) + ' WIB';
    
    const npmVal = document.getElementById('npmInput')?.value || 'Mahasiswa';
    const namaVal = document.getElementById('namaInput')?.value || '';
    const identitasLabel = namaVal ? `${namaVal} (${npmVal})` : `NPM: ${npmVal}`;

    ctx.fillText(`🕒 ${dateFormatted} • ${timeFormatted}  |  👤 ${identitasLabel}`, 16, canvas.height - bannerHeight + 36);

    // Watermark Label Verifikasi di Pojok Kanan
    ctx.font = 'bold 11px -apple-system, sans-serif';
    ctx.fillStyle = '#34d399';
    ctx.textAlign = 'right';
    ctx.fillText('StudentHub Verified', canvas.width - 16, canvas.height - bannerHeight + 14);

    // Konversi ke format DataURL JPEG
    const dataURL = canvas.toDataURL('image/jpeg', 0.85);
    document.getElementById('fotoBase64').value = dataURL;
    document.getElementById('fotoSavedName').value = ''; // Reset saved name karena ada foto baru

    // Tampilkan pratinjau gambar
    previewImg.src = dataURL;
    previewImg.style.display = 'block';
    video.style.display = 'none';
    guide.style.display = 'none';

    btnSnap.style.display = 'none';
    btnRetake.style.display = 'inline-flex';
    statusText.textContent = 'Foto selfie & koordinat GPS berhasil terkunci!';
    statusBadge.style.display = 'flex';
    isPhotoTaken = true;
}

// Mengulang foto selfie
function retakeSnapshot() {
    const video = document.getElementById('cameraVideo');
    const previewImg = document.getElementById('photoPreviewImg');
    const guide = document.getElementById('cameraGuide');
    const btnRetake = document.getElementById('btnRetake');
    const btnSnap = document.getElementById('btnSnap');
    const statusBadge = document.getElementById('photoStatusBadge');

    document.getElementById('fotoBase64').value = '';
    document.getElementById('fotoSavedName').value = '';
    previewImg.style.display = 'none';
    video.style.display = 'block';
    guide.style.display = 'flex';

    btnSnap.style.display = 'inline-flex';
    btnRetake.style.display = 'none';
    statusBadge.style.display = 'none';
    isPhotoTaken = false;

    // Nyalakan kamera jika belum menyala
    if (!videoStream) {
        initCamera();
    }
}

// Validasi Form sebelum dikirim (Ketat: Wajib Foto & Wajib GPS)
function handleFormSubmit(e) {
    const lat = document.getElementById('latitudeInput').value;
    const lng = document.getElementById('longitudeInput').value;
    const foto = document.getElementById('fotoBase64').value;
    const savedFoto = document.getElementById('fotoSavedName').value;

    if (!lat || !lng || lat === 'null' || lng === 'null') {
        alert("❌ ABSENSI DITOLAK!\n\nTitik lokasi GPS tidak terdeteksi!\nAnda WAJIB mengaktifkan GPS dan mengizinkan akses lokasi pada browser untuk dapat melakukan absensi.");
        initGeolocation(true);
        e.preventDefault();
        return false;
    }

    if (!isPhotoTaken || (!foto && !savedFoto)) {
        alert("❌ ABSENSI DITOLAK!\n\nFoto selfie bukti kehadiran belum diambil!\nSilakan klik tombol 'Ambil Foto Selfie' terlebih dahulu sebelum mengirim absensi.");
        e.preventDefault();
        return false;
    }

    return true;
}

// Theme UI
function getThemeIcons(isDark) {
    if (isDark) {
        return `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`;
    }
    return `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
}

function updateThemeUI() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.getElementById('themeIcon').innerHTML = getThemeIcons(isDark);
    document.getElementById('themeText').textContent = isDark ? 'Mode Terang' : 'Mode Gelap';
}

function toggleTheme() {
    if (document.documentElement.getAttribute('data-theme') === 'dark') {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('theme', 'light');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
    }
    updateThemeUI();
}

document.addEventListener("DOMContentLoaded", () => {
    updateThemeUI();
    initGeolocation();
    initCamera();
});
</script>
</body>
</html>
