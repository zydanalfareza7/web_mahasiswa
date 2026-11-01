<?php
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/koneksi.php';

$pesan = "";
$jenisPesan = "success";

if (isset($_GET['export']) && $_GET['export'] === 'true') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Data_Mahasiswa.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['NPM', 'NAMA', 'KELAS', 'JURUSAN']);
    
    $queryExport = mysqli_query($koneksi, "SELECT NPM, NAMA, KELAS, JURUSAN FROM tb_mhs ORDER BY NAMA ASC");
    while ($row = mysqli_fetch_assoc($queryExport)) {
        fputcsv($output, [$row['NPM'], $row['NAMA'], $row['KELAS'], $row['JURUSAN']]);
    }
    fclose($output);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "edit") {
        $npm = trim($_POST["npm"] ?? "");
        $nama = trim($_POST["nama"] ?? "");
        $kelas = trim($_POST["kelas"] ?? "");
        $jurusan = trim($_POST["jurusan"] ?? "");
        $npmLama = trim($_POST["npm_lama"] ?? "");

        if ($npm === "" || $nama === "" || $kelas === "" || $jurusan === "") {
            $pesan = "Semua kolom wajib diisi.";
            $jenisPesan = "error";
        } elseif (!is_numeric($npm)) {
            $pesan = "NPM harus berupa angka.";
            $jenisPesan = "error";
        } else {
            $stmt = mysqli_prepare($koneksi, "UPDATE tb_mhs SET NPM=?, NAMA=?, KELAS=?, JURUSAN=? WHERE NPM=?");
            mysqli_stmt_bind_param($stmt, "sssss", $npm, $nama, $kelas, $jurusan, $npmLama);
            if (mysqli_stmt_execute($stmt)) {
                header("Location: index.php?status=edit");
                exit;
            } else {
                $pesan = "Data gagal diperbarui. Pastikan NPM tidak sama dengan milik mahasiswa lain.";
                $jenisPesan = "error";
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($aksi === "hapus") {
        $npm = trim($_POST["npm"] ?? "");
        $stmt = mysqli_prepare($koneksi, "DELETE FROM tb_mhs WHERE NPM=?");
        mysqli_stmt_bind_param($stmt, "s", $npm);
        if (mysqli_stmt_execute($stmt)) {
            header("Location: index.php?status=hapus");
            exit;
        } else {
            $pesan = "Data gagal dihapus.";
            $jenisPesan = "error";
        }
        mysqli_stmt_close($stmt);
    } elseif ($aksi === "hapus_banyak") {
        $npmPilih = $_POST["npm_pilih"] ?? [];
        if (!empty($npmPilih)) {
            $terhapus = 0;
            foreach ($npmPilih as $npm) {
                $stmt = mysqli_prepare($koneksi, "DELETE FROM tb_mhs WHERE NPM = ?");
                mysqli_stmt_bind_param($stmt, "s", $npm);
                if (mysqli_stmt_execute($stmt)) {
                    $terhapus++;
                }
                mysqli_stmt_close($stmt);
            }
            header("Location: index.php?status=hapus_banyak&jml=$terhapus");
            exit;
        } else {
            $pesan = "Tidak ada data yang dipilih untuk dihapus.";
            $jenisPesan = "error";
        }
    } elseif ($aksi === "import") {
        if (isset($_FILES["file_csv"]) && $_FILES["file_csv"]["error"] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES["file_csv"]["tmp_name"];
            $fileName = $_FILES["file_csv"]["name"];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileExtension === "csv") {
                if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                    $berhasil = 0;
                    $gagal = 0;
                    fgetcsv($handle, 1000, ",");

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 4) {
                            $npm = trim($data[0]);
                            $nama = trim($data[1]);
                            $kelas = trim($data[2]);
                            $jurusan = trim($data[3]);

                            if ($npm !== "" && $nama !== "" && $kelas !== "" && $jurusan !== "") {
                                $cek = mysqli_prepare($koneksi, "SELECT NPM FROM tb_mhs WHERE NPM = ?");
                                mysqli_stmt_bind_param($cek, "s", $npm);
                                mysqli_stmt_execute($cek);
                                mysqli_stmt_store_result($cek);

                                if (mysqli_stmt_num_rows($cek) == 0) {
                                    $stmt = mysqli_prepare($koneksi, "INSERT INTO tb_mhs (NPM, NAMA, KELAS, JURUSAN) VALUES (?, ?, ?, ?)");
                                    mysqli_stmt_bind_param($stmt, "ssss", $npm, $nama, $kelas, $jurusan);
                                    if (mysqli_stmt_execute($stmt)) { $berhasil++; } else { $gagal++; }
                                    mysqli_stmt_close($stmt);
                                } else {
                                    $gagal++;
                                }
                                mysqli_stmt_close($cek);
                            }
                        }
                    }
                    fclose($handle);
                    header("Location: index.php?status=import&berhasil=$berhasil&gagal=$gagal");
                    exit;
                }
            } else {
                $pesan = "Format file harus .csv";
                $jenisPesan = "error";
            }
        }
    }
}

if (isset($_GET["status"])) {
    $status = $_GET["status"];
    if ($status === "import") {
        $b = $_GET["berhasil"] ?? 0;
        $g = $_GET["gagal"] ?? 0;
        $pesan = "Import selesai! Berhasil memasukkan $b data ($g data dilewati).";
    } elseif ($status === "hapus_banyak") {
        $jml = $_GET["jml"] ?? 0;
        $pesan = "Berhasil menghapus $jml data mahasiswa terpilih.";
    } else {
        $daftarPesan = [
            "tambah" => "Data mahasiswa berhasil ditambahkan.",
            "edit" => "Data mahasiswa berhasil diperbarui.",
            "hapus" => "Data mahasiswa berhasil dihapus."
        ];
        if (isset($daftarPesan[$status])) $pesan = $daftarPesan[$status];
    }
}

$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$pilihKelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
$batas = isset($_GET['batas']) ? (int)$_GET['batas'] : 5;
if (!in_array($batas, [5, 10, 25, 50])) { $batas = 5; }

$halamanAktif = isset($_GET["halaman"]) ? (int)$_GET["halaman"] : 1;
$halamanAktif = ($halamanAktif > 0) ? $halamanAktif : 1;
$mulai = ($halamanAktif > 1) ? ($halamanAktif * $batas) - $batas : 0;

$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($cari !== '') {
    $whereClause .= " AND (NPM LIKE ? OR NAMA LIKE ? OR JURUSAN LIKE ?)";
    $likeCari = "%" . $cari . "%";
    $params[] = $likeCari;
    $params[] = $likeCari;
    $params[] = $likeCari;
    $types .= "sss";
}

if ($pilihKelas !== '') {
    $whereClause .= " AND KELAS = ?";
    $params[] = $pilihKelas;
    $types .= "s";
}

$queryTotal = "SELECT COUNT(*) AS total FROM tb_mhs $whereClause";
$stmtTotal = mysqli_prepare($koneksi, $queryTotal);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmtTotal, $types, ...$params);
}
mysqli_stmt_execute($stmtTotal);
$resultTotal = mysqli_stmt_get_result($stmtTotal);
$rowTotal = mysqli_fetch_assoc($resultTotal);
$totalData = $rowTotal['total'];
$totalHalaman = ceil($totalData / $batas);
mysqli_stmt_close($stmtTotal);

$queryData = "SELECT NPM, NAMA, KELAS, JURUSAN FROM tb_mhs $whereClause ORDER BY NAMA ASC LIMIT ?, ?";
$stmtData = mysqli_prepare($koneksi, $queryData);

$bindParams = $params;
$bindParams[] = $mulai;
$bindParams[] = $batas;
$bindTypes = $types . "ii";

mysqli_stmt_bind_param($stmtData, $bindTypes, ...$bindParams);
mysqli_stmt_execute($stmtData);
$resultData = mysqli_stmt_get_result($stmtData);

$mahasiswa = [];
while ($row = mysqli_fetch_assoc($resultData)) {
    $mahasiswa[] = $row;
}
mysqli_stmt_close($stmtData);

$dataKelasUnik = mysqli_query($koneksi, "SELECT DISTINCT KELAS FROM tb_mhs ORDER BY KELAS ASC");
$kelasUnik = [];
while ($r = mysqli_fetch_assoc($dataKelasUnik)) {
    $kelasUnik[$r['KELAS']] = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Mahasiswa | StudentHub</title>
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

/* ================= ANIMASI FULL ================= */
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

.panel{background:var(--white);border:1px solid var(--line);border-radius:17px;box-shadow:0 4px 15px #172b4d04;overflow:hidden;transition:background .4s, border .4s}
.panel-head{padding:22px 23px 17px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.panel-head h2{margin:0;font-size:16px;letter-spacing:-.3px}
.panel-head p{font-size:12px;color:var(--muted);margin:7px 0 0}
.table-tools{display:flex;gap:10px;padding:17px 21px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:170px}
.search-wrap span{position:absolute;left:12px;top:11px;color:var(--muted)}
.search-wrap input{width:100%;border:1px solid var(--line);border-radius:9px;padding:11px 12px 11px 35px;font-size:12px;outline:none;background:var(--bg);color:var(--ink);transition:border-color .2s}
.search-wrap input:focus{border-color:var(--blue)}
.filter{border:1px solid var(--line);border-radius:9px;padding:11px 10px;background:var(--bg);font-size:12px;color:var(--ink);max-width:145px;outline:none}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;white-space:nowrap}
thead{background:var(--bg)}
th{font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);text-align:left;padding:13px 17px;font-weight:800}
td{padding:15px 17px;border-top:1px solid var(--line);font-size:12px;color:var(--ink)}
tbody tr{transition: background .15s ease;}
tbody tr:hover{background:var(--bg)}
.student{display:flex;align-items:center;gap:10px}
.student-avatar{height:34px;width:34px;border-radius:11px;display:grid;place-items:center;font-weight:800;font-size:12px;color:var(--blue);background:var(--bg)}
.student-name{font-weight:750;color:var(--ink);font-size:12px}
.student-npm{font-size:10px;color:var(--muted);margin-top:4px}
.class-tag{display:inline-block;background:var(--bg);color:var(--blue);border-radius:7px;padding:6px 8px;font-size:10px;font-weight:800}
.actions{display:flex;gap:6px}
.icon-btn{border:1px solid var(--line);background:var(--white);border-radius:8px;width:31px;height:31px;cursor:pointer;color:var(--muted);font-size:14px;transition:all .2s}
.icon-btn:hover{background:var(--bg);color:var(--blue);border-color:var(--blue);transform: translateY(-2px);}
.icon-btn.delete:hover{background:#fff0f1;color:var(--red);border-color:#ffd8dc}
.table-foot{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:15px 20px;border-top:1px solid var(--line);color:var(--muted);font-size:11px;flex-wrap:wrap}
.empty{text-align:center;padding:35px 15px;color:var(--muted);font-size:13px}
.toast{padding:13px 16px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:650;animation: popIn 0.4s ease forwards;}
.toast.success{background:#e9f9f2;color:#14825f}
.toast.error{background:#fff0f1;color:#c63e4d}

.btn-import{background:var(--green);color:#fff;border:0;border-radius:10px;padding:9px 15px;font-size:12px;font-weight:750;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:transform .15s, opacity .2s}
.btn-import:hover{opacity:0.9}
.btn-import:active{transform:scale(0.97)}
.btn-export{background:var(--blue);color:#fff;border:0;border-radius:10px;padding:9px 15px;font-size:12px;font-weight:750;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:transform .15s, background .2s}
.btn-export:hover{background:var(--blue2)}
.btn-export:active{transform:scale(0.97)}
.btn-danger-bulk{background:var(--red);color:#fff;border:0;border-radius:10px;padding:9px 15px;font-size:12px;font-weight:750;cursor:pointer;display:inline-flex;align-items:center;gap:6px;opacity:0.6;pointer-events:none;transition:all .2s}
.btn-danger-bulk.active{opacity:1;pointer-events:auto}
.btn-danger-bulk.active:hover{opacity:0.9; transform: translateY(-1px);}

.pagination{display:flex;gap:5px;align-items:center}
.page-link{padding:6px 12px;border:1px solid var(--line);background:var(--white);color:var(--ink);border-radius:7px;font-size:12px;font-weight:700;text-decoration:none;transition:all .2s}
.page-link:hover, .page-link.active{background:var(--blue);color:#fff;border-color:var(--blue)}

.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;z-index:100;backdrop-filter: blur(3px);}
.modal-content{background:var(--white);padding:25px;border-radius:15px;width:100%;max-width:400px;box-shadow:0 10px 30px rgba(0,0,0,0.3);animation: popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;}
.field{margin-bottom:15px}
.field label{display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--ink)}
.field input{width:100%;border:1px solid var(--line);background:var(--bg);border-radius:9px;padding:11px;font-size:13px;color:var(--ink);outline:none;transition:border-color .2s}
.field input:focus{border-color:var(--blue)}
.btn{border:0;border-radius:10px;padding:10px 15px;font-size:12px;font-weight:750;cursor:pointer;transition:transform .15s}
.btn:active{transform:scale(0.97)}
.btn-primary{background:var(--blue);color:#fff}
.btn-danger{background:var(--red);color:#fff}
.btn-light{background:var(--line);color:var(--ink)}
.detail-item{margin-bottom:12px;font-size:13px}
.detail-item span{display:block;color:var(--muted);font-size:11px;font-weight:600;margin-bottom:3px}
.detail-item strong{font-size:15px;color:var(--ink)}

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
  <a class="nav-link" href="tambah.php"><span class="nav-ico">＋</span><span>Tambah Mahasiswa</span></a>
  <a class="nav-link active" href="index.php"><span class="nav-ico">☷</span><span>Data Mahasiswa</span></a>
  <a class="nav-link" href="rekap_absen.php"><span class="nav-ico">📋</span><span>Rekap Absensi</span></a>
  <a class="nav-link" href="logout.php" style="color:var(--red);margin-top:20px"><span class="nav-ico">🚪</span><span>Keluar (Logout)</span></a>
  <div class="side-bottom"><strong>StudentHub</strong>Aplikasi pengelolaan data & absensi mahasiswa.</div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<main class="main">
  <header class="topbar">
    <div class="topbar-title-wrap" style="display:flex;align-items:center;gap:14px;"><button type="button" class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Buka Menu">☰</button><div><div class="eyebrow">STUDENT MANAGEMENT</div><h1>Data Mahasiswa</h1><div class="sub">Kelola, cari, dan ubah data seluruh mahasiswa terdaftar.</div></div></div>
    <div class="topbar-right">
      <button class="theme-toggle" id="themeToggleBtn" onclick="toggleTheme()">
        <span id="themeIcon">🌙</span> <span id="themeText">Dark Mode</span>
      </button>
      <div class="profile"><div class="avatar">A</div><div><strong>Administrator</strong><small>Pengelola Data</small></div></div>
    </div>
  </header>

  <?php if ($pesan !== ""): ?>
    <div class="toast <?= e($jenisPesan) ?>" id="toast"><?= e($pesan) ?></div>
  <?php endif; ?>

  <section class="panel table-panel">
    <div class="panel-head">
      <div>
        <h2>Daftar Seluruh Mahasiswa</h2>
        <p>Semua data mahasiswa yang tersimpan di database.</p>
      </div>
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="index.php?export=true" class="btn-export">📥 Export Excel</a>
        <button type="button" class="btn-danger-bulk" id="bulkDeleteBtn">🗑️ Hapus Terpilih (<span id="countSelected">0</span>)</button>
        <button type="button" class="btn-import" id="openImportModal">📂 Import CSV</button>
      </div>
    </div>
    
    <form method="GET" action="index.php" id="filterForm">
      <div class="table-tools">
        <div style="display: flex; gap: 10px; flex: 1; flex-wrap: wrap; align-items: center;">
          <div class="search-wrap">
            <span>⌕</span>
            <input type="text" name="cari" id="searchInput" placeholder="Cari nama, NPM, jurusan..." value="<?= e($cari) ?>">
          </div>
          
          <select class="filter" name="kelas" id="classFilter" onchange="document.getElementById('filterForm').submit()">
            <option value="">Semua Kelas</option>
            <?php foreach (array_keys($kelasUnik) as $k): ?>
              <option value="<?= e($k) ?>" <?= ($pilihKelas === $k) ? 'selected' : '' ?>><?= e($k) ?></option>
            <?php endforeach; ?>
          </select>

          <select class="filter" name="batas" id="limitFilter" onchange="document.getElementById('filterForm').submit()" style="max-width: 120px;">
            <option value="5" <?= ($batas == 5) ? 'selected' : '' ?>>5 per hal</option>
            <option value="10" <?= ($batas == 10) ? 'selected' : '' ?>>10 per hal</option>
            <option value="25" <?= ($batas == 25) ? 'selected' : '' ?>>25 per hal</option>
            <option value="50" <?= ($batas == 50) ? 'selected' : '' ?>>50 per hal</option>
          </select>
        </div>
      </div>
    </form>

    <form id="bulkDeleteForm" method="POST">
      <input type="hidden" name="aksi" value="hapus_banyak">
      
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
              <th>Mahasiswa</th>
              <th>Kelas</th>
              <th>Jurusan</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody id="studentRows">
          <?php if (count($mahasiswa) > 0): ?>
            <?php foreach ($mahasiswa as $m): ?>
              <tr data-npm="<?= e($m['NPM']) ?>">
                <td style="text-align: center;"><input type="checkbox" name="npm_pilih[]" value="<?= e($m['NPM']) ?>" class="row-checkbox"></td>
                <td><div class="student"><div class="student-avatar"><?= e(strtoupper(substr($m["NAMA"], 0, 1))) ?></div><div><div class="student-name"><?= e($m["NAMA"]) ?></div><div class="student-npm"><?= e($m["NPM"]) ?></div></div></div></td>
                <td><span class="class-tag"><?= e($m["KELAS"]) ?></span></td>
                <td><?= e($m["JURUSAN"]) ?></td>
                <td><div class="actions">
                  <button type="button" class="icon-btn detail-btn" title="Lihat Detail" aria-label="Lihat Detail" data-npm="<?= e($m['NPM']) ?>" data-nama="<?= e($m['NAMA']) ?>" data-kelas="<?= e($m['KELAS']) ?>" data-jurusan="<?= e($m['JURUSAN']) ?>">👁</button>
                  <button type="button" class="icon-btn edit-btn" title="Edit data" aria-label="Edit data" data-npm="<?= e($m['NPM']) ?>" data-nama="<?= e($m['NAMA']) ?>" data-kelas="<?= e($m['KELAS']) ?>" data-jurusan="<?= e($m['JURUSAN']) ?>">✎</button>
                  <button type="button" class="icon-btn delete icon-delete-trigger" data-npm="<?= e($m["NPM"]) ?>" data-nama="<?= e($m["NAMA"]) ?>" title="Hapus data" aria-label="Hapus data">⌫</button>
                </div></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="empty">Data mahasiswa tidak ditemukan.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </form>

    <div class="table-foot">
      <span>Menampilkan halaman <?= $halamanAktif ?> dari total <?= $totalHalaman > 0 ? $totalHalaman : 1 ?> halaman (Total <?= $totalData ?> data)</span>
      
      <?php if ($totalHalaman > 1): ?>
        <div class="pagination">
          <?php 
            $queryParamStr = "&cari=" . urlencode($cari) . "&kelas=" . urlencode($pilihKelas) . "&batas=" . $batas;
          ?>
          <?php if ($halamanAktif > 1): ?>
            <a class="page-link" href="?halaman=<?= $halamanAktif - 1 ?><?=$queryParamStr ?>">‹ Prev</a>
          <?php endif; ?>

          <?php for ($i = 1; $i <= $totalHalaman; $i++): ?>
            <a class="page-link <?= ($i == $halamanAktif) ? 'active' : '' ?>" href="?halaman=<?= $i ?><?= $queryParamStr ?>"><?= $i ?></a>
          <?php endfor; ?>

          <?php if ($halamanAktif <$totalHalaman): ?>
            <a class="page-link" href="?halaman=<?= $halamanAktif + 1 ?><?=$queryParamStr ?>">Next ›</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>

<!-- Modal-modal -->
<div class="modal" id="bulkDeleteModal">
  <div class="modal-content" style="text-align: center; padding: 30px 25px;">
    <div style="font-size: 40px; margin-bottom: 10px;">⚠️</div>
    <h3 style="margin: 0 0 10px; color: var(--ink);">Konfirmasi Hapus Massal</h3>
    <p style="font-size: 13px; color: var(--muted); margin: 0 0 20px;">Yakin ingin menghapus <strong id="bulkCountText">0</strong> data mahasiswa yang dicentang?</p>
    <div style="display: flex; gap: 10px;">
      <button type="button" class="btn btn-light" id="cancelBulkDelete" style="flex: 1;">Batal</button>
      <button type="button" class="btn btn-danger" id="confirmBulkDelete" style="flex: 1;">Ya, Hapus Semua</button>
    </div>
  </div>
</div>

<div class="modal" id="importModal">
  <div class="modal-content">
    <h3 style="margin-top:0; margin-bottom:10px; color:var(--green);">Import Data Mahasiswa</h3>
    <p style="font-size:12px; color:var(--muted); margin-bottom:15px;">Pilih file berformat <strong>.csv</strong> yang berisi data NPM, Nama, Kelas, dan Jurusan.</p>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="aksi" value="import">
      <div class="field">
        <label>Pilih File CSV</label>
        <input type="file" name="file_csv" accept=".csv" required style="padding: 8px;">
      </div>
      <div style="font-size:11px; color:var(--muted); margin-bottom:15px;">
        Belum punya file contoh? <a href="data_mahasiswa.csv" download style="color:var(--blue); font-weight:700;">Download Template CSV</a>
      </div>
      <div style="display:flex; gap:10px;">
        <button type="button" class="btn btn-light" id="closeImport" style="flex:1;">Batal</button>
        <button type="submit" class="btn btn-primary" style="flex:1; background:var(--green);">Mulai Import</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="deleteModal">
  <div class="modal-content" style="text-align: center; padding: 30px 25px;">
    <div style="font-size: 40px; margin-bottom: 10px;">⚠️</div>
    <h3 style="margin: 0 0 10px; color: var(--ink);">Konfirmasi Hapus</h3>
    <p style="font-size: 13px; color: var(--muted); margin: 0 0 20px;">Yakin ingin menghapus data mahasiswa <strong id="delStudentName" style="color: var(--ink);"></strong>?</p>
    <form id="deleteForm" method="POST">
      <input type="hidden" name="aksi" value="hapus">
      <input type="hidden" name="npm" id="delNpmInput">
      <div style="display: flex; gap: 10px;">
        <button type="button" class="btn btn-light" id="cancelDelete" style="flex: 1;">Batal</button>
        <button type="submit" class="btn btn-danger" style="flex: 1;">Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="detailModal">
  <div class="modal-content">
    <h3 style="margin-top:0; margin-bottom:15px; color:var(--blue);">Detail Mahasiswa</h3>
    <div class="detail-item"><span>NPM</span><strong id="detNpm">-</strong></div>
    <div class="detail-item"><span>Nama Lengkap</span><strong id="detNama">-</strong></div>
    <div class="detail-item"><span>Kelas</span><strong id="detKelas">-</strong></div>
    <div class="detail-item"><span>Jurusan</span><strong id="detJurusan">-</strong></div>
    <div style="margin-top:20px; text-align:right;">
      <button type="button" class="btn btn-primary" id="closeDetail" style="width:100%;">Tutup</button>
    </div>
  </div>
</div>

<div class="modal" id="editModal">
  <div class="modal-content">
    <h3 style="margin-top:0; margin-bottom:15px;">Edit Mahasiswa</h3>
    <form method="POST">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="npm_lama" id="editNpmLama">
      <div class="field"><label>NPM</label><input type="text" name="npm" id="editNpm" required></div>
      <div class="field"><label>Nama Mahasiswa</label><input type="text" name="nama" id="editNama" required></div>
      <div class="field"><label>Kelas</label><input type="text" name="kelas" id="editKelas" required></div>
      <div class="field"><label>Jurusan</label><input type="text" name="jurusan" id="editJurusan" required></div>
      <div style="display:flex; gap:10px; margin-top:20px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">Simpan Perubahan</button>
        <button type="button" class="btn btn-light" id="closeModal">Batal</button>
      </div>
    </form>
  </div>
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

const searchInput = document.getElementById('searchInput');
let searchTimeout;
if (searchInput) {
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('cari', searchInput.value);
            urlParams.set('kelas', document.getElementById('classFilter').value);
            urlParams.set('batas', document.getElementById('limitFilter').value);
            urlParams.set('halaman', 1);
            window.location.href = 'index.php?' + urlParams.toString();
        }, 600);
    });
}

const selectAll = document.getElementById('selectAll');
const rowCheckboxes = document.querySelectorAll('.row-checkbox');
const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
const countSelected = document.getElementById('countSelected');

function updateBulkButton() {
    const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
    countSelected.textContent = checkedCount;
    if (checkedCount > 0) {
        bulkDeleteBtn.classList.add('active');
    } else {
        bulkDeleteBtn.classList.remove('active');
    }
}

if(selectAll) {
    selectAll.addEventListener('change', (e) => {
        rowCheckboxes.forEach(cb => {
            cb.checked = e.target.checked;
        });
        updateBulkButton();
    });
}

rowCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateBulkButton);
});

const bulkDeleteModal = document.getElementById('bulkDeleteModal');
const bulkDeleteForm = document.getElementById('bulkDeleteForm');

if(bulkDeleteBtn) {
    bulkDeleteBtn.addEventListener('click', () => {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        if (checkedCount > 0) {
            document.getElementById('bulkCountText').textContent = checkedCount;
            bulkDeleteModal.style.display = 'flex';
        }
    });
}

document.getElementById('cancelBulkDelete').addEventListener('click', () => {
    bulkDeleteModal.style.display = 'none';
});

document.getElementById('confirmBulkDelete').addEventListener('click', () => {
    bulkDeleteForm.submit();
});

const importModal = document.getElementById('importModal');
document.getElementById('openImportModal').addEventListener('click', () => {
  importModal.style.display = 'flex';
});
document.getElementById('closeImport').addEventListener('click', () => {
  importModal.style.display = 'none';
});

const deleteModal = document.getElementById('deleteModal');
document.querySelectorAll('.icon-delete-trigger').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('delNpmInput').value = btn.dataset.npm;
    document.getElementById('delStudentName').textContent = btn.dataset.nama;
    deleteModal.style.display = 'flex';
  });
});
document.getElementById('cancelDelete').addEventListener('click', () => {
  deleteModal.style.display = 'none';
});

const detailModal = document.getElementById('detailModal');
document.querySelectorAll('.detail-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('detNpm').textContent = btn.dataset.npm;
    document.getElementById('detNama').textContent = btn.dataset.nama;
    document.getElementById('detKelas').textContent = btn.dataset.kelas;
    document.getElementById('detJurusan').textContent = btn.dataset.jurusan;
    detailModal.style.display = 'flex';
  });
});
document.getElementById('closeDetail').addEventListener('click', () => {
  detailModal.style.display = 'none';
});

const editModal = document.getElementById('editModal');
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('editNpmLama').value = btn.dataset.npm;
    document.getElementById('editNpm').value = btn.dataset.npm;
    document.getElementById('editNama').value = btn.dataset.nama;
    document.getElementById('editKelas').value = btn.dataset.kelas;
    document.getElementById('editJurusan').value = btn.dataset.jurusan;
    editModal.style.display = 'flex';
  });
});
document.getElementById('closeModal').addEventListener('click', () => {
  editModal.style.display = 'none';
});

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