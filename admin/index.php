<?php
require '../config.php';
// Memeriksa hak akses pengguna
need(['admin', 'agen']);

// Mengambil data pengguna yang sedang login
$u = user();
$adm = ($u['role'] === 'admin');
$me = $u['id'];

// Fungsi untuk mengambil data statistik berdasarkan status
function grp($pdo, $sql, $p)
{
    $s = $pdo->prepare($sql);
    $s->execute($p);

    return $s->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Mengambil statistik properti
$ps = grp(
    $pdo,
    'SELECT status, COUNT(*)
    FROM properti
    WHERE (? = 1 OR id_agen = ?)
    GROUP BY status',
    [$adm ? 1 : 0, $me]
);

// Mengambil statistik permintaan pembeli
$rq = grp(
    $pdo,
    'SELECT r.status, COUNT(*)
    FROM permintaan r
    JOIN properti p USING (id_properti)
    WHERE (? = 1 OR p.id_agen = ?)
    GROUP BY r.status',
    [$adm ? 1 : 0, $me]
);

// Menghitung total nilai penjualan yang selesai
$t = $pdo->prepare(
    'SELECT COALESCE(SUM(harga_final), 0)
    FROM transaksi_penjualan t
    JOIN properti p USING (id_properti)
    WHERE t.status = "Selesai"
    AND (? = 1 OR p.id_agen = ?)'
);
$t->execute([$adm ? 1 : 0, $me]);

// Menentukan judul halaman dan memuat header
$title = 'Dashboard';
require '../includes/header.php';
?>

<!-- Konten Dashboard -->
<h2>Dashboard</h2>
<p>
    <a class="btn" href="properti.php">
        Kelola Properti
    </a>
    <a class="btn sec" href="permintaan.php">
        Prospek &amp; Survei
    </a>
</p>

<!-- Statistik Properti -->
<h3>Properti</h3>
<div class="stat">
    <?php foreach (['Tersedia', 'Dalam Negosiasi', 'Terjual'] as $k): ?>
        <div class="box">
            <b><?= $ps[$k] ?? 0 ?></b>
            <?= $k ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Statistik Permintaan Pembeli -->

<h3>Permintaan Pembeli</h3>
<div class="stat">
    <?php foreach (
        ['Diajukan', 'Dihubungi', 'Survei Dijadwalkan', 'Selesai']
        as $k
    ): ?>
        <div class="box">
            <b><?= $rq[$k] ?? 0 ?></b>
            <?= $k ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Total Nilai Penjualan -->

<h3>Nilai Penjualan Selesai</h3>

<div class="box">
    <b style="font-size: 24px; color: #0f2a43;">
        <?= rp($t->fetchColumn()) ?>
    </b>
</div>

<?php require '../includes/footer.php'; ?>