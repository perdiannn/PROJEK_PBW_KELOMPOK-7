<?php
require_once '../config.php';
need(['agen']);
$u = user();

// Statistik Agen
$sProperti = $pdo->prepare('SELECT COUNT(*) FROM properti WHERE id_agen = ?');
$sProperti->execute([$u['id']]);
$totalProperti = (int)$sProperti->fetchColumn();

$sAktif = $pdo->prepare('SELECT COUNT(*) FROM permintaan r JOIN properti p ON p.id_properti = r.id_properti WHERE p.id_agen = ? AND r.status NOT IN ("Selesai", "Dibatalkan")');
$sAktif->execute([$u['id']]);
$totalAktif = (int)$sAktif->fetchColumn();

$sUsulan = $pdo->prepare('SELECT COUNT(*) FROM usulan x JOIN permintaan r ON r.id_permintaan = x.id_permintaan JOIN properti p ON p.id_properti = r.id_properti WHERE p.id_agen = ? AND x.status = "Menunggu" AND x.id_pengusul != ?');
$sUsulan->execute([$u['id'], $u['id']]);
$totalMenunggu = (int)$sUsulan->fetchColumn();

$sDeal = $pdo->prepare('SELECT COUNT(*) FROM transaksi_penjualan t JOIN properti p ON p.id_properti = t.id_properti WHERE p.id_agen = ? AND t.status = "Selesai"');
$sDeal->execute([$u['id']]);
$totalDeal = (int)$sDeal->fetchColumn();

$title = 'Dashboard Agen';
require '../includes/header.php';
?>
<h2>Dashboard Agen: <?= e($u['nama']) ?></h2>
<p class="mut">Kelola komunikasi dengan calon pembeli, atur jadwal survei lapangan, lakukan tawar-menawar harga, dan catat transaksi final properti.</p>

<div class="stat">
    <div class="box">
        <b><?= $totalProperti ?></b>
        Properti Ditangani
    </div>
    <div class="box">
        <b><?= $totalAktif ?></b>
        Pengajuan Berjalan
    </div>
    <div class="box" style="<?= $totalMenunggu > 0 ? 'border-color:#f59e0b' : '' ?>">
        <b style="<?= $totalMenunggu > 0 ? 'color:#d97706' : '' ?>"><?= $totalMenunggu ?></b>
        Usulan Butuh Respons
    </div>
    <div class="box">
        <b style="color:#059669"><?= $totalDeal ?></b>
        Transaksi Penjualan Sukses
    </div>
</div>

<div class="actions" style="margin-top:20px">
    <a class="btn" href="permintaan.php">Lihat Pengajuan & Chat Pembeli &rarr;</a>
    <a class="btn sec" href="<?= BASE ?>/">Lihat Katalog Publik</a>
</div>

<?php require '../includes/footer.php'; ?>
