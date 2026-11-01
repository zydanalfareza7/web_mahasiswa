<?php
session_start();
require_once __DIR__ . '/koneksi.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    $stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE username = ?");
    if (!$stmt) {
        $error = "Tabel 'users' belum ada di database! Silakan import database.sql di phpMyAdmin.";
    } else {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $row['password']) || $password === $row['password']) {
                $_SESSION['admin_logged'] = true;
                $_SESSION['admin_user'] = $row['username'];
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Password yang kamu masukkan salah!";
            }
        } else {
            $error = "Username admin tidak ditemukan!";
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | StudentHub</title>
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
    --emerald-bg: #f0fdf4;
    --emerald-border: #bbf7d0;
    --emerald-text: #15803d;
    --emerald-sub: #166534;
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
    --emerald-bg: rgba(16, 185, 129, 0.08);
    --emerald-border: rgba(52, 211, 153, 0.22);
    --emerald-text: #34d399;
    --emerald-sub: #94a3b8;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    background-color: var(--bg);
    background-image: 
        radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.07) 0px, transparent 45%),
        radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.05) 0px, transparent 45%);
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    display: grid;
    place-items: center;
    min-height: 100vh;
    padding: 24px;
    position: relative;
    transition: background-color .3s ease, color .3s ease;
}

/* Theme Toggle Button */
.theme-toggle {
    position: absolute;
    top: 24px;
    right: 24px;
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
    transition: all .2s ease;
}
.theme-toggle:hover {
    color: var(--ink);
    border-color: var(--blue);
}

/* Login Card */
.login-card {
    background: var(--card-bg);
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 36px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.06), 0 4px 6px -4px rgba(15, 23, 42, 0.02);
    animation: fadeIn .4s ease-out;
    transition: background-color .3s, border-color .3s, box-shadow .3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Brand Header */
.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}
.brand-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff;
    display: grid;
    place-items: center;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
.brand-title {
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.4px;
    color: var(--ink);
    line-height: 1.2;
}
.brand-subtitle {
    font-size: 11px;
    font-weight: 500;
    color: var(--muted);
}

h1 {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.5px;
    margin: 0 0 6px;
    color: var(--ink);
}
p.subtitle {
    color: var(--muted);
    font-size: 13px;
    margin: 0 0 24px;
    line-height: 1.5;
}

/* Form Fields */
.field {
    margin-bottom: 16px;
}
.field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 7px;
    color: var(--ink);
}
.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.input-icon {
    position: absolute;
    left: 14px;
    color: var(--muted);
    pointer-events: none;
    display: flex;
    align-items: center;
}
.field input {
    width: 100%;
    border: 1px solid var(--line);
    background: var(--input-bg);
    border-radius: 10px;
    padding: 11px 14px 11px 40px;
    font-size: 13px;
    color: var(--ink);
    font-family: inherit;
    outline: none;
    transition: border-color .2s, box-shadow .2s, background-color .3s;
}
.field input.has-toggle {
    padding-right: 42px;
}
.field input:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.toggle-password {
    position: absolute;
    right: 10px;
    background: transparent;
    border: 0;
    color: var(--muted);
    padding: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    border-radius: 6px;
    transition: color .2s;
}
.toggle-password:hover {
    color: var(--ink);
}

/* Submit Button */
.btn-primary {
    background: var(--blue);
    color: #fff;
    border: 0;
    border-radius: 10px;
    padding: 12px;
    width: 100%;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    margin-top: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    transition: background-color .2s, transform .1s, box-shadow .2s;
}
.btn-primary:hover {
    background: var(--blue-hover);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.32);
}
.btn-primary:active {
    transform: scale(0.985);
}

/* Divider */
.divider {
    margin: 22px 0 18px;
    border-top: 1px solid var(--line);
    text-align: center;
    position: relative;
}
.divider span {
    background: var(--card-bg);
    padding: 0 12px;
    position: relative;
    top: -10px;
    font-size: 11px;
    color: var(--muted);
    font-weight: 650;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    transition: background-color .3s;
}

/* Mahasiswa Access Card (Tanpa ceklis, modern & clean) */
.btn-student {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--emerald-bg);
    border: 1px solid var(--emerald-border);
    border-radius: 12px;
    padding: 12px 14px;
    text-decoration: none;
    transition: all .2s ease;
}
.btn-student:hover {
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(52, 211, 153, 0.4);
    transform: translateY(-1px);
}
.btn-student:active {
    transform: translateY(0);
}
.btn-student-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.student-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: rgba(16, 185, 129, 0.15);
    color: var(--emerald-text);
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.student-text {
    display: flex;
    flex-direction: column;
}
.student-title {
    font-size: 13px;
    font-weight: 650;
    color: var(--emerald-text);
}
.student-desc {
    font-size: 11px;
    color: var(--emerald-sub);
    margin-top: 2px;
}
.arrow-icon {
    color: var(--emerald-text);
    opacity: 0.7;
    transition: transform .2s ease, opacity .2s ease;
}
.btn-student:hover .arrow-icon {
    transform: translateX(3px);
    opacity: 1;
}

/* Error message */
.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    padding: 11px 13px;
    border-radius: 10px;
    font-size: 12px;
    margin-bottom: 18px;
    font-weight: 550;
    display: flex;
    align-items: center;
    gap: 8px;
}
[data-theme="dark"] .error {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.25);
    color: #fca5a5;
}

/* Footer Credit */
.login-footer {
    margin-top: 22px;
    text-align: center;
    font-size: 11px;
    color: var(--muted);
}
</style>
<script>
if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
}
</script>
</head>
<body>

<button class="theme-toggle" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Ganti Tema">
  <span id="themeIcon"></span>
  <span id="themeText">Mode Gelap</span>
</button>

<div class="login-card">
  <div class="brand">
    <div class="brand-icon">
      <!-- Graduation / Student Cap Icon -->
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
      </svg>
    </div>
    <div>
      <div class="brand-title">StudentHub</div>
      <div class="brand-subtitle">Portal Manajemen & Presensi</div>
    </div>
  </div>

  <h1>Selamat Datang</h1>
  <p class="subtitle">Masuk dengan akun admin atau akses layanan presensi mandiri mahasiswa.</p>

  <?php if ($error !== ""): ?>
    <div class="error">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <label for="username">Username Admin</label>
      <div class="input-wrap">
        <span class="input-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
        </span>
        <input type="text" id="username" name="username" placeholder="Masukkan username admin" required autofocus>
      </div>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-wrap">
        <span class="input-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </span>
        <input type="password" id="password" name="password" class="has-toggle" placeholder="Masukkan password" required>
        <button type="button" class="toggle-password" id="togglePasswordBtn" onclick="togglePasswordVisibility()" aria-label="Lihat Password">
          <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-primary">
      <span>Masuk ke Dashboard</span>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="5" y1="12" x2="19" y2="12"/>
        <polyline points="12 5 19 12 12 19"/>
      </svg>
    </button>
  </form>

  <div class="divider"><span>Akses Mahasiswa</span></div>

  <!-- Tautan Khusus Mahasiswa (Tanpa Ceklis, Bersih & Modern) -->
  <a href="absen.php" class="btn-student">
    <div class="btn-student-left">
      <div class="student-icon-box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
      </div>
      <div class="student-text">
        <span class="student-title">Halaman Absensi Mandiri</span>
        <span class="student-desc">Input kehadiran kuliah mahasiswa</span>
      </div>
    </div>
    <svg class="arrow-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="9 18 15 12 9 6"/>
    </svg>
  </a>

  <div class="login-footer">
    StudentHub &bull; by Zydan
  </div>
</div>

<script>
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

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = `
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
        `;
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
        `;
    }
}

document.addEventListener("DOMContentLoaded", updateThemeUI);
</script>
</body>
</html>
