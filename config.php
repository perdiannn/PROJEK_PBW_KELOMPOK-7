<?php
session_start();
$pdo = new PDO('mysql:host=localhost;dbname=sipro;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
define('BASE', '/sipro'); // sesuaikan dengan nama folder di htdocs
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function rp($n)
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}
function user()
{
    return $_SESSION['u'] ?? null;
}
function need($r)
{
    $u = user();
    if (!$u || !in_array($u['role'], $r)) {
        header('Location: ' . BASE . '/login.php');
        exit;
    }
}
function csrf()
{
    $_SESSION['t'] = $_SESSION['t'] ?? bin2hex(random_bytes(16));
    return '<input type="hidden" name="t" value="' . $_SESSION['t'] . '">';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? '')) {
    http_response_code(400);
    die('Token tidak valid, muat ulang halaman.');
}
