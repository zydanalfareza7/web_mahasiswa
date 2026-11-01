<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/koneksi.php';

// Proses Hapus Data Absensi dari Dashboard
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";
    if ($aksi === "hapus_absen") {
        $idAbsen = intval($_POST["id_absen"] ?? 0);
        if ($idAbsen > 0) {
            $qFoto = mysqli_prepare($koneksi, "SELECT foto FROM tb_absensi WHERE id = ?");
            if ($qFoto) {
                mysqli_stmt_bind_param($qFoto, "i", $idAbsen);
                mysqli_stmt_execute($qFoto);
                $resFoto = mysqli_stmt_get_result($qFoto);
                if ($rowFoto = mysqli_fetch_assoc($resFoto)) {
                    if (!empty($rowFoto['foto'])) {
                        $filePath = __DIR__ . '/uploads/absensi/' . $rowFoto['foto'];
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }
                mysqli_stmt_close($qFoto);
            }

            $stmtDel = mysqli_prepare($koneksi, "DELETE FROM tb_absensi WHERE id = ?");
            if ($stmtDel) {
                mysqli_stmt_bind_param($stmtDel, "i", $idAbsen);
                mysqli_stmt_execute($stmtDel);
                mysqli_stmt_close($stmtDel);
                header("Location: dashboard.php?status=hapus_sukses");
                exit;
            }
        }
    }
}

// 1. Hitung Total Mahasiswa
$qMhs = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_mhs");
$totalMhs = mysqli_fetch_assoc($qMhs)['total'];

// 2. Hitung Total Kelas Unik
$qKelas = mysqli_query($koneksi, "SELECT COUNT(DISTINCT KELAS) as total FROM tb_mhs");
$totalKelas = mysqli_fetch_assoc($qKelas)['total'];

// 3. Hitung Total Jurusan Unik
$qJurusan = mysqli_query($koneksi, "SELECT COUNT(DISTINCT JURUSAN) as total FROM tb_mhs");
$totalJurusan = mysqli_fetch_assoc($qJurusan)['total'];

// 4. Hitung Total Absen Hari Ini
$tanggalHariIni = date('Y-m-d');
$qAbsenHariIni = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tb_absensi WHERE tanggal = '$tanggalHariIni'");
$totalAbsenHariIni = mysqli_fetch_assoc($qAbsenHariIni)['total'];

// 5. Ambil 5 Mahasiswa Terdaftar Terbaru
$resultMhsTerbaru = mysqli_query($koneksi, "SELECT * FROM tb_mhs ORDER BY id DESC LIMIT 5");

// 6. Ambil 5 Riwayat Absensi Terbaru
$queryAbsenTerbaru = "SELECT tb_absensi.*, tb_mhs.NAMA, tb_mhs.KELAS, tb_mhs.JURUSAN 
                      FROM tb_absensi 
                      JOIN tb_mhs ON tb_absensi.npm = tb_mhs.NPM 
                      ORDER BY tb_absensi.tanggal DESC, tb_absensi.jam_masuk DESC 
                      LIMIT 5";
$resultAbsenTerbaru = mysqli_query($koneksi, $queryAbsenTerbaru);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Utama | StudentHub</title>
<style>
:root {
    --blue: #4361ee;
    --blue2: #3652d9;
    --ink: #182230;
    --muted: #788397;
    --line: #e9edf5;
    --bg: #f5f7fb;
    --white: #fff;
    --green: #16a879;
    --red: #e45161;
}

[data-theme="dark"] {
    --blue: #4863f7;
    --blue2: #5872ff;
    --ink: #f1f5f9;
    --muted: #94a3b8;
    --line: #334155;
    --bg: #0f172a;
    --white: #1e293b;
}

*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--ink);transition:background .4s, color .4s;overflow-x:hidden}

/* ================= ANIMASI DASHBOARD ================= */
@keyframes fadeInContent {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes floatIcon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}

.layout{display:flex;min-height:100vh}
.sidebar{width:245px;background:var(--white);border-right:1px solid var(--line);padding:28px 18px;position:fixed;inset:0 auto 0 0;display:flex;flex-direction:column;z-index:5;transition:background .4s, border .4s}
.brand{display:flex;align-items:center;gap:11px;padding:0 10px 35px;font-size:19px;font-weight:800;letter-spacing:-.5px}
.brand-icon{width:38px;height:38px;border-radius:12px;background:var(--blue);color:white;display:grid;place-items:center;font-size:20px;box-shadow:0 7px 16px #4361ee38;animation: floatIcon 3s infinite ease-in-out;}

.nav-label{font-size:10px;color:var(--muted);font-weight:800;letter-spacing:1.4px;padding:0 12px 12px}
.nav-link{display:flex;align-items:center;gap:13px;padding:13px 14px;border-radius:11px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:650;margin-bottom:5px;transition:all .25s ease}
.nav-link.active,.nav-link:hover{background:var(--bg);color:var(--blue);transform: translateX(4px);}
.nav-ico{font-size:17px;width:20px;text-align:center}

.side-bottom{margin-top:auto;background:var(--bg);border-radius:14px;padding:16px;font-size:12px;color:var(--muted);line-height:1.6}
.side-bottom strong{color:var(--ink);display:block;font-size:13px;margin-bottom:4px}

.main{
    margin-left:245px;
    width:calc(100% - 245px);
    padding:30px 38px 45px;
    max-width:1700px;
    animation: fadeInContent 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:29px}
.eyebrow{color:var(--blue);font-weight:800;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;margin-bottom:7px}
.topbar h1{margin:0;font-size:27px;letter-spacing:-.8px}
.sub{color:var(--muted);font-size:13px;margin-top:7px}
.topbar-right{display:flex;align-items:center;gap:12px}

.theme-toggle{background:var(--white);border:1px solid var(--line);color:var(--ink);padding:10px 14px;border-radius:13px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 15px #172b4d04;transition:all .2s}
.theme-toggle:hover{border-color:var(--blue);transform: translateY(-2px);}

.profile{display:flex;align-items:center;gap:11px;background:var(--white);border:1px solid var(--line);padding:8px 13px;border-radius:13px;transition:background .4s, border .4s}
.avatar{width:38px;height:38px;background:var(--bg);color:var(--blue);border-radius:12px;display:grid;place-items:center;font-weight:800}
.profile strong{font-size:12px;display:block}
.profile small{font-size:11px;color:var(--muted)}

.stats-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:20px;margin-bottom:30px}
.stat-card{
    background:var(--white);
    border:1px solid var(--line);
    border-radius:17px;
    padding:22px;
    box-shadow:0 4px 15px #172b4d04;
    position:relative;
    overflow:hidden;
    transition: transform .25s ease, box-shadow .25s ease, background .4s, border .4s;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px #172b4d0a;
}
.stat-title{font-size:12px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.stat-value{font-size:32px;font-weight:800;margin-top:10px;letter-spacing:-1px}
.stat-icon{position:absolute;right:20px;bottom:20px;width:38px;height:38px;border-radius:10px;background:var(--bg);color:var(--blue);display:grid;place-items:center;font-size:18px}

.banner{background:var(--white);border:1px solid var(--line);border-radius:17px;padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:30px;box-shadow:0 4px 15px #172b4d04;transition:background .4s, border .4s}
.banner h3{margin:0 0 6px;font-size:15px;letter-spacing:-.2px}
.banner p{margin:0;color:var(--muted);font-size:12px}
.btn-banner{background:var(--blue);color:#fff;text-decoration:none;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:750;white-space:nowrap;box-shadow:0 4px 12px #4361ee38;transition:transform .15s, opacity .2s}
.btn-banner:hover{opacity:0.9}
.btn-banner:active{transform: scale(0.97);}

.panel{background:var(--white);border:1px solid var(--line);border-radius:17px;box-shadow:0 4px 15px #172b4d04;overflow:hidden;margin-bottom:30px;transition:background .4s, border .4s}
.panel-head{padding:22px 23px 17px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}
.panel-head h2{margin:0;font-size:16px;letter-spacing:-.3px}
.panel-head a{font-size:12px;color:var(--blue);text-decoration:none;font-weight:700;transition:opacity .2s}
.panel-head a:hover{opacity: 0.8; text-decoration:underline}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;white-space:nowrap}
thead{background:var(--bg)}
th{font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);text-align:left;padding:13px 17px;font-weight:800}
td{padding:14px 17px;border-top:1px solid var(--line);font-size:12px;color:var(--ink);vertical-align:middle}
tbody tr{transition: background .15s ease;}
tbody tr:hover{background:var(--bg)}
.student{display:flex;align-items:center;gap:10px}
.student-avatar{height:32px;width:32px;border-radius:10px;display:grid;place-items:center;font-weight:800;font-size:11px;color:var(--blue);background:var(--bg)}
.student-name{font-weight:750;font-size:12px}
.student-npm{font-size:10px;color:var(--muted);margin-top:2px}
.class-tag{display:inline-block;background:var(--bg);color:var(--blue);border-radius:6px;padding:5px 7px;font-size:10px;font-weight:800}
.status-badge{display:inline-block;padding:5px 9px;border-radius:6px;font-size:10px;font-weight:800}
.status-hadir{background:#e9f9f2;color:#14825f}
.status-izin{background:#eef2ff;color:#4361ee}
.status-sakit{background:#fffbeb;color:#d97706}
.status-alpa{background:#fff0f1;color:#c63e4d}
.empty{text-align:center;padding:25px;color:var(--muted);font-size:12px}

/* ================= FOTO & KOORDINAT BADGE ================= */
.photo-thumb {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid var(--line);
    transition: transform .2s, border-color .2s;
    background: #0f172a;
}
.photo-thumb:hover {
    transform: scale(1.12);
    border-color: var(--blue);
}
.no-photo-badge {
    font-size: 11px;
    color: var(--muted);
    background: var(--bg);
    padding: 3px 6px;
    border-radius: 6px;
}
.maps-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(67, 97, 238, 0.08);
    color: var(--blue);
    border: 1px solid rgba(67, 97, 238, 0.2);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 650;
    text-decoration: none;
    transition: all .2s;
}
.maps-link:hover {
    background: var(--blue);
    color: #fff;
}
.btn-delete {
    background: rgba(228, 81, 97, 0.08);
    color: var(--red);
    border: 1px solid rgba(228, 81, 97, 0.22);
    border-radius: 7px;
    padding: 4px 8px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    transition: all .2s ease;
}
.btn-delete:hover {
    background: var(--red);
    color: #fff;
}

.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: var(--white);
    border: 1px solid var(--line);
    border-radius: 18px;
    max-width: 500px;
    width: 100%;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--line);
}
.modal-title { font-weight: 800; font-size: 15px; margin: 0; color: var(--ink); }
.modal-close {
    background: transparent;
    border: 0;
    color: var(--muted);
    font-size: 22px;
    cursor: pointer;
    line-height: 1;
}
.modal-body { padding: 20px; text-align: center; }
.modal-img {
    width: 100%;
    max-height: 360px;
    object-fit: contain;
    border-radius: 12px;
    background: #0f172a;
}
.modal-info {
    margin-top: 14px;
    text-align: left;
    background: var(--bg);
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

/* ================= RESPONSIVE SIDEBAR MOBILE ================= */
.sidebar-toggle-btn {
    display: none;
    align-items: center;
    justify-content: center;
    background: var(--white);
    border: 1px solid var(--line);
    color: var(--ink);
    width: 42px;
    height: 42px;
    border-radius: 12px;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    transition: all .2s;
    flex-shrink: 0;
}
.sidebar-toggle-btn:hover {
    border-color: var(--blue);
    color: var(--blue);
}
.sidebar-close-btn {
    display: none;
    background: none;
    border: none;
    font-size: 20px;
    color: var(--muted);
    cursor: pointer;
    margin-left: auto;
    padding: 6px;
    border-radius: 8px;
    line-height: 1;
}
.sidebar-close-btn:hover {
    color: var(--ink);
    background: var(--bg);
}
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(2px);
    z-index: 998;
    opacity: 0;
    transition: opacity .3s ease;
    pointer-events: none;
}
@media (max-width: 900px) {
    .sidebar-toggle-btn {
        display: flex;
    }
    .sidebar-close-btn {
        display: block;
    }
    .sidebar {
        transform: translateX(-100%);
        transition: transform .3s cubic-bezier(0.16, 1, 0.3, 1), background .4s, border .4s;
        z-index: 999;
        box-shadow: 10px 0 30px rgba(0,0,0,0.18);
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .sidebar-overlay.active {
        display: block;
        opacity: 1;
        pointer-events: auto;
    }
    .main {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 20px 16px 40px !important;
    }
    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    .topbar-right {
        width: 100%;
        justify-content: space-between;
    }
    .topbar-title-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
    }
}
</style>
<script>
if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }
}
</script>
</head>
<body>
<div class="layout">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="brand-icon">S</div><span class="brand-text">StudentHub</span><button type="button" class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Tutup Menu">✕</button></div>
  <div class="nav-label">MENU UTAMA</div>
  <a class="nav-link active" href="dashboard.php"><span class="nav-ico">▦</span><span>Dashboard</span></a>
  <a class="nav-link" href="tambah.php"><span class="nav-ico">＋</span><span>Tambah Mahasiswa</span></a>
  <a class="nav-link" href="index.php"><span class="nav-ico">☷</span><span>Data Mahasiswa</span></a>
  <a class="nav-link" href="rekap_absen.php"><span class="nav-ico">📋</span><span>Rekap Absensi</span></a>
  <a class="nav-link" href="logout.php" style="color:var(--red);margin-top:20px"><span class="nav-ico">🚪</span><span>Keluar (Logout)</span></a>
  <div class="side-bottom"><strong>StudentHub</strong>Aplikasi pengelolaan data & absensi mahasiswa.</div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<main class="main">
  <header class="topbar">
    <div class="topbar-title-wrap" style="display:flex;align-items:center;gap:14px;"><button type="button" class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Buka Menu">☰</button><div><div class="eyebrow">STUDENT MANAGEMENT</div><h1>Dashboard Utama</h1><div class="sub">Selamat datang kembali, Administrator. Berikut ringkasan sistem akademik.</div></div></div>
    <div class="topbar-right">
      <button class="theme-toggle" id="themeToggleBtn" onclick="toggleTheme()">
        <span id="themeIcon">🌙</span> <span id="themeText">Dark Mode</span>
      </button>
      <div class="profile"><div class="avatar">A</div><div><strong>Administrator</strong><small>Pengelola Data</small></div></div>
    </div>
  </header>

  <!-- Statistik Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-title">Total Mahasiswa</div>
      <div class="stat-value"><?= e($totalMhs) ?></div>
      <div class="stat-icon">🎓</div>
    </div>
    <div class="stat-card">
      <div class="stat-title">Total Kelas</div>
      <div class="stat-value"><?= e($totalKelas) ?></div>
      <div class="stat-icon">🏫</div>
    </div>
    <div class="stat-card">
      <div class="stat-title">Total Jurusan</div>
      <div class="stat-value"><?= e($totalJurusan) ?></div>
      <div class="stat-icon">📚</div>
    </div>
    <div class="stat-card">
      <div class="stat-title">Absen Hari Ini</div>
      <div class="stat-value" style="color: var(--green);"><?= e($totalAbsenHariIni) ?></div>
      <div class="stat-icon">✔</div>
    </div>
  </div>

  <div class="banner">
    <div>
      <h3>Butuh mendaftarkan data mahasiswa baru?</h3>
      <p>Gunakan form input cepat atau buka halaman manajemen data mahasiswa.</p>
    </div>
    <a href="tambah.php" class="btn-banner">+ Tambah Mahasiswa Baru</a>
  </div>

  <!-- Tabel Absensi Terbaru -->
  <section class="panel">
    <div class="panel-head">
      <h2>Riwayat Absensi Terbaru</h2>
      <a href="rekap_absen.php">Lihat Semua Rekap →</a>
    </div>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Jam Masuk</th>
            <th>Foto</th>
            <th>Mahasiswa</th>
            <th>Kelas</th>
            <th>Jurusan</th>
            <th>Status</th>
            <th>Lokasi</th>
            <th style="text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($resultAbsenTerbaru && mysqli_num_rows($resultAbsenTerbaru) > 0): ?>
          <?php while ($row = mysqli_fetch_assoc($resultAbsenTerbaru)): ?>
            <?php 
              $classBadge = "status-hadir";
              if ($row['status'] === 'Izin') $classBadge = "status-izin";
              elseif ($row['status'] === 'Sakit') $classBadge = "status-sakit";
              elseif ($row['status'] === 'Alpa') $classBadge = "status-alpa";

              $hasPhoto = (!empty($row['foto']) && file_exists(__DIR__ . '/uploads/absensi/' . $row['foto']));
              $hasGps = (!empty($row['latitude']) && !empty($row['longitude']));
              $latVal = $row['latitude'] ?? '';
              $lngVal = $row['longitude'] ?? '';
            ?>
            <tr>
              <td><?= e($row['tanggal']) ?></td>
              <td><strong><?= e($row['jam_masuk']) ?></strong></td>
              <td>
                <?php if ($hasPhoto): ?>
                  <img src="uploads/absensi/<?= e($row['foto']) ?>" 
                       class="photo-thumb" 
                       alt="Selfie <?= e($row['NAMA']) ?>"
                       onclick="openPhotoModal('uploads/absensi/<?= e($row['foto']) ?>', '<?= e($row['NAMA']) ?>', '<?= e($row['npm']) ?>', '<?= e($latVal) ?>', '<?= e($lngVal) ?>', '<?= e($row['tanggal']) ?> <?= e($row['jam_masuk']) ?>', '<?= e($row['id']) ?>')"
                       title="Lihat bukti foto">
                <?php else: ?>
                  <span class="no-photo-badge">-</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="student">
                  <div class="student-avatar"><?= e(strtoupper(substr($row["NAMA"], 0, 1))) ?></div>
                  <div>
                    <div class="student-name"><?= e($row["NAMA"]) ?></div>
                    <div class="student-npm"><?= e($row["npm"]) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="class-tag"><?= e($row["KELAS"]) ?></span></td>
              <td><?= e($row["JURUSAN"]) ?></td>
              <td><span class="status-badge <?= $classBadge ?>"><?= e($row['status']) ?></span></td>
              <td>
                <?php if ($hasGps): ?>
                  <a href="https://www.google.com/maps?q=<?= urlencode($latVal) ?>,<?= urlencode($lngVal) ?>" 
                     target="_blank" 
                     rel="noopener noreferrer" 
                     class="maps-link" 
                     title="Buka Google Maps">
                    <span>📍 Maps &nearr;</span>
                  </a>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:11px;">-</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data absensi <?= e($row['NAMA']) ?>? Foto bukti selfie juga akan dihapus.');">
                  <input type="hidden" name="aksi" value="hapus_absen">
                  <input type="hidden" name="id_absen" value="<?= e($row['id']) ?>">
                  <button type="submit" class="btn-delete" title="Hapus Data Absensi">
                    <span>Hapus</span>
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="9" class="empty">Belum ada data absensi yang tercatat hari ini.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Tabel Mahasiswa Terbaru -->
  <section class="panel">
    <div class="panel-head">
      <h2>Mahasiswa Terdaftar Terbaru</h2>
      <a href="index.php">Lihat Semua Data →</a>
    </div>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>NPM</th>
            <th>Nama Mahasiswa</th>
            <th>Kelas</th>
            <th>Jurusan</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($resultMhsTerbaru && mysqli_num_rows($resultMhsTerbaru) > 0): ?>
          <?php while ($rowMhs = mysqli_fetch_assoc($resultMhsTerbaru)): ?>
            <tr>
              <td><strong><?= e($rowMhs['NPM']) ?></strong></td>
              <td>
                <div class="student">
                  <div class="student-avatar"><?= e(strtoupper(substr($rowMhs["NAMA"], 0, 1))) ?></div>
                  <div class="student-name"><?= e($rowMhs["NAMA"]) ?></div>
                </div>
              </td>
              <td><span class="class-tag"><?= e($rowMhs["KELAS"]) ?></span></td>
              <td><?= e($rowMhs["JURUSAN"]) ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="4" class="empty">Belum ada data mahasiswa.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</div>

<!-- Lightbox Modal Foto Absensi Ber-Watermark -->
<div class="modal-overlay" id="photoModalOverlay" onclick="closePhotoModal(event)">
  <div class="modal-box" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3 class="modal-title">Bukti Presensi & Stempel GPS</h3>
      <button type="button" class="modal-close" onclick="closePhotoModal()" aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body">
      <img id="modalImg" class="modal-img" src="" alt="Foto Selfie Absensi">
      <div class="modal-info">
        <div>
          <strong id="modalStudentName" style="display:block;font-size:13px;color:var(--ink);margin-bottom:2px;">-</strong>
          <span id="modalDateTime" style="color:var(--muted);">-</span>
        </div>
        <div id="modalMapsWrap" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;"></div>
      </div>
    </div>
  </div>
</div>

<script>
function openPhotoModal(imgSrc, name, npm, lat, lng, dateTime, idAbsen) {
    document.getElementById('modalImg').src = imgSrc;
    document.getElementById('modalStudentName').textContent = `${name} (${npm})`;
    document.getElementById('modalDateTime').textContent = `Presensi: ${dateTime}`;

    const mapsWrap = document.getElementById('modalMapsWrap');
    let mapsBtn = '';
    if (lat && lng && lat !== 'null' && lat !== '') {
        mapsBtn = `
            <a href="https://www.google.com/maps?q=${encodeURIComponent(lat)},${encodeURIComponent(lng)}" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="maps-link" style="padding:6px 10px;font-size:11px;">
              📍 Maps &nearr;
            </a>
        `;
    }

    let deleteBtn = '';
    if (idAbsen) {
        deleteBtn = `
            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus data absensi ini beserta bukti fotonya?');">
              <input type="hidden" name="aksi" value="hapus_absen">
              <input type="hidden" name="id_absen" value="${idAbsen}">
              <button type="submit" class="btn-delete" style="padding:5px 9px;font-size:11px;">
                🗑️ Hapus
              </button>
            </form>
        `;
    }

    mapsWrap.innerHTML = mapsBtn + deleteBtn;
    document.getElementById('photoModalOverlay').classList.add('active');
}

function closePhotoModal() {
    document.getElementById('photoModalOverlay').classList.remove('active');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePhotoModal();
});

function updateThemeUI() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.getElementById('themeIcon').textContent = isDark ? '☀️' : '🌙';
    document.getElementById('themeText').textContent = isDark ? 'Light Mode' : 'Dark Mode';
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

document.addEventListener("DOMContentLoaded", updateThemeUI);
</script>
</body>
</html>
