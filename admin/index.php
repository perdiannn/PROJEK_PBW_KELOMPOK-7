<?php
require_once '../config.php';
need(['admin', 'agen']);

$u = user();
if ($u['role'] === 'agen') {
    go(BASE . '/agen/');
}

// 1. Data Statistik Properti
$psRaw = $pdo->query('SELECT status, COUNT(*) FROM properti GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$ps = [
    'Tersedia'        => (int)($psRaw['Tersedia'] ?? 0),
    'Dalam Negosiasi' => (int)($psRaw['Dalam Negosiasi'] ?? 0),
    'Terjual'         => (int)($psRaw['Terjual'] ?? 0),
];
$totalProperti = array_sum($ps);

// 2. Data Statistik Permintaan Pembeli
$rqRaw = $pdo->query('SELECT status, COUNT(*) FROM permintaan GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$rq = [
    'Diajukan'           => (int)($rqRaw['Diajukan'] ?? 0),
    'Dihubungi'          => (int)($rqRaw['Dihubungi'] ?? 0),
    'Survei Dijadwalkan' => (int)($rqRaw['Survei Dijadwalkan'] ?? 0),
    'Selesai'            => (int)($rqRaw['Selesai'] ?? 0),
];
$totalPermintaan = array_sum($rq);

// 3. Data Nilai Penjualan Selesai
$totalNilaiPenjualan = (float)$pdo->query('SELECT COALESCE(SUM(harga_final), 0) FROM transaksi_penjualan WHERE status = "Selesai"')->fetchColumn();

$title = 'Dashboard Admin';
require '../includes/header.php';
?>

<h2>Dashboard Admin</h2>

<!-- Tombol Aksi Cepat Admin -->
<div class="actions" style="margin-bottom:24px; display:flex; gap:12px; flex-wrap:wrap;">
    <a class="btn" href="properti.php">Kelola Properti</a>
    <a class="btn" href="akun.php">Kelola Akun Agen</a>
    <a class="btn" href="permintaan.php">Monitoring Pengajuan</a>
</div>

<!-- 1. Nilai Penjualan Selesai -->
<h3 style="margin-bottom:8px">Nilai Penjualan Selesai</h3>
<div class="v-omzet-card">
    <div>
        <div class="v-omzet-val"><?= rp($totalNilaiPenjualan) ?></div>
        <div class="v-omzet-sub">Akumulasi total nilai transaksi penjualan properti yang telah selesai</div>
    </div>
</div>

<!-- 2. Statistik Properti -->
<h3 style="margin:28px 0 8px 0">Properti</h3>

<div class="v-stat-grid">
    <div class="v-card green">
        <div>
            <div class="v-card-num"><?= $ps['Tersedia'] ?></div>
            <div class="v-card-lbl">Tersedia</div>
        </div>
    </div>
    <div class="v-card amber">
        <div>
            <div class="v-card-num"><?= $ps['Dalam Negosiasi'] ?></div>
            <div class="v-card-lbl">Dalam Negosiasi</div>
        </div>
    </div>
    <div class="v-card red">
        <div>
            <div class="v-card-num"><?= $ps['Terjual'] ?></div>
            <div class="v-card-lbl">Terjual</div>
        </div>
    </div>
</div>

<?php if ($totalProperti > 0): ?>
    <?php
    $pctTersedia = round(($ps['Tersedia'] / $totalProperti) * 100);
    $pctNego     = round(($ps['Dalam Negosiasi'] / $totalProperti) * 100);
    $pctTerjual  = 100 - $pctTersedia - $pctNego;
    ?>
<?php endif; ?>

<!-- 3. Permintaan Pembeli -->
<h3 style="margin:24px 0 8px 0">Permintaan Pembeli</h3>

<div class="v-stat-grid">
    <div class="v-card blue">
        <div>
            <div class="v-card-num"><?= $rq['Diajukan'] ?></div>
            <div class="v-card-lbl">Diajukan</div>
        </div>

    </div>
    <div class="v-card cyan">
        <div>
            <div class="v-card-num"><?= $rq['Dihubungi'] ?></div>
            <div class="v-card-lbl">Dihubungi</div>
        </div>
    </div>
    <div class="v-card amber">
        <div>
            <div class="v-card-num"><?= $rq['Survei Dijadwalkan'] ?></div>
            <div class="v-card-lbl">Survei Dijadwalkan</div>
        </div>
    </div>
    <div class="v-card green">
        <div>
            <div class="v-card-num"><?= $rq['Selesai'] ?></div>
            <div class="v-card-lbl">Selesai</div>
        </div>
    </div>
</div>

<?php if ($totalPermintaan > 0): ?>
    <?php
    $pctDiajukan = round(($rq['Diajukan'] / $totalPermintaan) * 100);
    $pctDihubungi = round(($rq['Dihubungi'] / $totalPermintaan) * 100);
    $pctSurvei = round(($rq['Survei Dijadwalkan'] / $totalPermintaan) * 100);
    $pctSelesai = 100 - $pctDiajukan - $pctDihubungi - $pctSurvei;
    ?>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>
