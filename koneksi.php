<?php
// Aktifkan laporan error agar tidak muncul pesan blank / HTTP ERROR 500 tanpa penjelasan
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Nonaktifkan automatic exception mysqli agar tidak langsung crash 500 di PHP 8.1+
mysqli_report(MYSQLI_REPORT_OFF);

// Konfigurasi Database InfinityFree
$host = "sql312.infinityfree.com";
$user = "if0_43310503";
$pass = "mahasewa2026"; 
$db   = "if0_43310503_db_mahasiswa"; 

$koneksi = mysqli_init();
mysqli_options($koneksi, MYSQLI_OPT_CONNECT_TIMEOUT, 3); // Timeout 3 detik agar tidak menggantung / timed out
$connected = @mysqli_real_connect($koneksi, $host, $user, $pass, $db);

if (!$connected) {
    die("
    <div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background: #fff5f5; border: 1px solid #fed7d7; border-radius: 12px; padding: 25px; max-width: 650px; margin: 40px auto; color: #2d3748; box-shadow: 0 10px 25px rgba(0,0,0,0.06);'>
        <h2 style='color: #e53e3e; margin-top: 0; font-size: 20px; display: flex; align-items: center; gap: 8px;'>
            ⚠️ Gagal Menghubungkan ke Database InfinityFree
        </h2>
        <p style='margin: 10px 0;'><strong>Pesan Error MySQL:</strong> <code style='background: #fee2e2; color: #991b1b; padding: 3px 6px; border-radius: 4px;'>" . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8') . "</code></p>
        <hr style='border: 0; border-top: 1px solid #fed7d7; margin: 15px 0;'>
        <p style='font-size: 14px; font-weight: 600; margin-bottom: 8px;'>Panduan Pemeriksaan di Dashboard InfinityFree:</p>
        <ol style='font-size: 13px; line-height: 1.7; padding-left: 20px; color: #4a5568;'>
            <li>Buka <strong>Control Panel</strong> &rarr; menu <strong>MySQL Databases</strong> (Basis Data MySQL).</li>
            <li>Pastikan database <code>$db</code> sudah Anda buat.</li>
            <li>Pastikan <strong>MySQL Host Name</strong> sama persis (cek apakah host-nya benar <code>$host</code> atau nomor lain).</li>
            <li>Pastikan password akun <code>$pass</code> sudah benar.</li>
        </ol>
    </div>
    ");
}

mysqli_set_charset($koneksi, "utf8mb4");

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
