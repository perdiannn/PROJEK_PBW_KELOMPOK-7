<?php require_once __DIR__ . '/../config.php';
$u = user(); ?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title ?? 'SIPRO') ?> | SIPRO</title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/style.css">
</head>

<body>
    <header class="nav">
        <div class="wrap"><a class="logo" href="<?= BASE ?>/">SIPRO</a>
            <nav><a href="<?= BASE ?>/">Properti</a>
                <?php if ($u): if ($u['role'] != 'pembeli'): ?><a href="<?= BASE ?>/admin/">Dashboard</a><?php else: ?><a href="<?= BASE ?>/akun.php">Pengajuan Saya</a><?php endif; ?><a href="<?= BASE ?>/logout.php">Keluar (<?= e($u['nama']) ?>)</a>
                <?php else: ?><a href="<?= BASE ?>/login.php">Masuk</a><a class="btn" href="<?= BASE ?>/register.php">Daftar</a><?php endif; ?></nav>
        </div>
    </header>
    <main class="wrap">