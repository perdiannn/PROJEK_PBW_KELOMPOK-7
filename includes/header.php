<?php
require_once __DIR__ . '/../config.php';
$u = user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Sistem Informasi Penjualan Properti') ?> | SIPRO</title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/style.css">
</head>
<body>
    <header class="nav">
        <div class="wrap navinner">
            <a class="logo" href="<?= BASE ?>/">SIPRO</a>
            <nav>
                <a href="<?= BASE ?>/">Katalog Properti</a>
                <?php if ($u): ?>
                    <?php if ($u['role'] === 'admin'): ?>
                        <a href="<?= BASE ?>/admin/">Dashboard</a>
                        <a href="<?= BASE ?>/admin/properti.php">Properti</a>
                        <a href="<?= BASE ?>/admin/akun.php">Kelola Agen</a>
                    <?php elseif ($u['role'] === 'agen'): ?>
                        <a href="<?= BASE ?>/agen/">Dashboard Agen</a>
                        <a href="<?= BASE ?>/agen/permintaan.php">Pengajuan Pembeli</a>
                    <?php else: ?>
                        <a href="<?= BASE ?>/akun.php">Pengajuan Saya</a>
                    <?php endif; ?>
                    <span class="user-pill"><?= e($u['nama']) ?> (<?= e(ucfirst($u['role'])) ?>)</span>
                    <a class="btn-logout" href="<?= BASE ?>/logout.php">Keluar</a>
                <?php else: ?>
                    <a href="<?= BASE ?>/login.php">Masuk</a>
                    <a class="btn" href="<?= BASE ?>/register.php">Daftar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="wrap">
