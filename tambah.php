<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/koneksi.php';

$pesan = "";
$jenisPesan = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $npm     = trim($_POST["npm"] ?? "");
    $nama    = trim($_POST["nama"] ?? "");
    $kelas   = trim($_POST["kelas"] ?? "");
    $jurusan = trim($_POST["jurusan"] ?? "");

    if ($npm === "" || $nama === "" || $kelas === "" || $jurusan === "") {
        $pesan = "Semua kolom wajib diisi!";
        $jenisPesan = "error";
    } else {
        $cek = mysqli_prepare($koneksi, "SELECT NPM FROM tb_mhs WHERE NPM = ?");
        mysqli_stmt_bind_param($cek, "s", $npm);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $pesan = "NPM <strong>{$npm}</strong> sudah terdaftar di database!";
            $jenisPesan = "error";
        } else {
            mysqli_stmt_close($cek);

            $stmt = mysqli_prepare($koneksi, "INSERT INTO tb_mhs (NPM, NAMA, KELAS, JURUSAN) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssss", $npm, $nama, $kelas, $jurusan);

            if (mysqli_stmt_execute($stmt)) {
                $pesan = "Data mahasiswa berhasil ditambahkan!";
                $jenisPesan = "success";
            } else {
                $pesan = "Gagal menyimpan data ke database.";
                $jenisPesan = "error";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tambah Mahasiswa | StudentHub</title>
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

@keyframes fadeInContent {
    from { opacity: 0; transform: translateY(18px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes floatIcon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}
@keyframes popIn {
    0% { transform: scale(0.95); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
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

.panel{background:var(--white);border:1px solid var(--line);border-radius:17px;box-shadow:0 4px 15px #172b4d04;padding:30px;max-width:700px;transition:background .4s, border .4s}
.field{margin-bottom:18px}
.field label{display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--ink)}
.field input{width:100%;border:1px solid var(--line);background:var(--bg);border-radius:9px;padding:12px;font-size:13px;color:var(--ink);outline:none;transition:border-color .2s}
.field input:focus{border-color:var(--blue)}
.btn-primary{background:var(--blue);color:#fff;border:0;border-radius:10px;padding:11px 20px;font-size:13px;font-weight:750;cursor:pointer;box-shadow:0 4px 12px #4361ee38;transition:transform .15s, opacity .2s}
.btn-primary:hover{opacity:0.9}
.btn-primary:active{transform: scale(0.97);}
.toast{padding:13px 16px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:650;animation: popIn 0.4s ease forwards;}
.toast.success{background:#e9f9f2;color:#14825f}
.toast.error{background:#fff0f1;color:#c63e4d}

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
  <a class="nav-link" href="dashboard.php"><span class="nav-ico">▦</span><span>Dashboard</span></a>
  <a class="nav-link active" href="tambah.php"><span class="nav-ico">＋</span><span>Tambah Mahasiswa</span></a>
  <a class="nav-link" href="index.php"><span class="nav-ico">☷</span><span>Data Mahasiswa</span></a>
  <a class="nav-link" href="rekap_absen.php"><span class="nav-ico">📋</span><span>Rekap Absensi</span></a>
  <a class="nav-link" href="logout.php" style="color:var(--red);margin-top:20px"><span class="nav-ico">🚪</span><span>Keluar (Logout)</span></a>
  <div class="side-bottom"><strong>StudentHub</strong>Aplikasi pengelolaan data & absensi mahasiswa.</div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<main class="main">
  <header class="topbar">
    <div class="topbar-title-wrap" style="display:flex;align-items:center;gap:14px;"><button type="button" class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Buka Menu">☰</button><div><div class="eyebrow">REGISTRATION</div><h1>Tambah Mahasiswa Baru</h1><div class="sub">Daftarkan data mahasiswa ke dalam sistem database.</div></div></div>
    <div class="topbar-right">
      <button class="theme-toggle" id="themeToggleBtn" onclick="toggleTheme()">
        <span id="themeIcon">🌙</span> <span id="themeText">Dark Mode</span>
      </button>
      <div class="profile"><div class="avatar">A</div><div><strong>Administrator</strong><small>Pengelola Data</small></div></div>
    </div>
  </header>

  <?php if ($pesan !== ""): ?>
    <div class="toast <?= e($jenisPesan) ?>"><?= $pesan ?></div>
  <?php endif; ?>

  <section class="panel">
    <form method="POST">
      <div class="field">
        <label>NPM</label>
        <input type="text" name="npm" placeholder="Contoh: 51423046" required autofocus>
      </div>
      <div class="field">
        <label>Nama Lengkap</label>
        <input type="text" name="nama" placeholder="Contoh: Zydan Alfareza" required>
      </div>
      <div class="field">
        <label>Kelas</label>
        <input type="text" name="kelas" placeholder="Contoh: 4IA23" required>
      </div>
      <div class="field">
        <label>Jurusan</label>
        <input type="text" name="jurusan" placeholder="Contoh: Informatika" required>
      </div>
      <div style="margin-top: 25px;">
        <button type="submit" class="btn-primary">💾 Simpan Data Mahasiswa</button>
      </div>
    </form>
  </section>
</main>
</div>

<script>
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

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }
}
</script>
</body>
</html>