<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Database (Laragon 6 Default)
$DB_HOST = 'localhost';
$DB_NAME = 'sipro';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage() . "<br>Pastikan MySQL di Laragon sudah dinyalakan dan database 'sipro' sudah diimpor.");
}

// Deteksi Otomatis Base URL Proyek (Bekerja di http://localhost/sistem_penjualan_properti)
if (!defined('BASE')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $appDir = str_replace('\\', '/', realpath(__DIR__));
    if ($docRoot !== '' && strpos($appDir, $docRoot) === 0) {
        $sub = trim(substr($appDir, strlen($docRoot)), '/');
        define('BASE', $sub === '' ? '' : '/' . $sub);
    } else {
        define('BASE', '/sistem_penjualan_properti');
    }
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function rp($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tgl($s, $showTime = true): string
{
    if (!$s) return '-';
    $format = $showTime ? 'd/m/Y H:i' : 'd/m/Y';
    return date($format, strtotime($s));
}

function user(): ?array
{
    return $_SESSION['u'] ?? null;
}

function need(array $roles): void
{
    global $pdo;
    $u = user();
    if (!$u || !in_array($u['role'], $roles, true)) {
        header('Location: ' . BASE . '/login.php');
        exit;
    }

    // Jika akun agen dinonaktifkan oleh admin, segera keluarkan dari sesi
    if ($u['role'] === 'agen') {
        $st = $pdo->prepare('SELECT status_akun FROM users WHERE id_user = ?');
        $st->execute([$u['id']]);
        if ($st->fetchColumn() !== 'Aktif') {
            unset($_SESSION['u']);
            header('Location: ' . BASE . '/login.php?err=nonaktif');
            exit;
        }
    }
}

function flash(?string $m = null, bool $err = false): ?array
{
    if ($m !== null) {
        $_SESSION['flash'] = [$m, $err];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function tampil_flash(): void
{
    if ($f = flash()) {
        $cls = $f[1] ? 'msg err' : 'msg ok';
        echo '<div class="' . $cls . '">' . e($f[0]) . '</div>';
    }
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    // Set token session cadangan untuk kompatibilitas
    $_SESSION['t'] = $_SESSION['csrf'];
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '"><input type="hidden" name="t" value="' . e($_SESSION['csrf']) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf'] ?? $_POST['t'] ?? '';
        $sessionToken = $_SESSION['csrf'] ?? $_SESSION['t'] ?? '';
        if (!$sessionToken || !hash_equals($sessionToken, $token)) {
            http_response_code(400);
            exit('Token keamanan tidak valid. Silakan muat ulang halaman.');
        }
    }
}

function go(string $url): void
{
    header('Location: ' . $url);
    exit;
}

verify_csrf();
require_once __DIR__ . '/includes/properti_tipe.php';
