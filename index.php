<?php require 'config.php';
$title = 'Beranda';
$w = ['1=1'];
$p = [];
if (!empty($_GET['lokasi'])) {
    $w[] = 'lokasi LIKE ?';
    $p[] = '%' . $_GET['lokasi'] . '%';
}
foreach (['tipe', 'status'] as $k) if (!empty($_GET[$k])) {
    $w[] = "$k=?";
    $p[] = $_GET[$k];
}
if (!empty($_GET['min'])) {
    $w[] = 'harga>=?';
    $p[] = (int)$_GET['min'];
}
if (!empty($_GET['max'])) {
    $w[] = 'harga<=?';
    $p[] = (int)$_GET['max'];
}
$s = $pdo->prepare('SELECT p.*,(SELECT nama_file FROM foto_properti f WHERE f.id_properti=p.id_properti ORDER BY id_foto LIMIT 1) foto FROM properti p WHERE ' . implode(' AND ', $w) . ' ORDER BY created_at DESC');
$s->execute($p);
$rows = $s->fetchAll();
require 'includes/header.php'; ?>
<div class="hero">
    <h1>Temukan Properti Impian Anda</h1>
    <p>Rumah, ruko, tanah, dan apartemen terverifikasi dengan harga transparan.</p>
</div>
<form class="box f" method="get">
    <div class="row">
        <input name="lokasi" placeholder="Lokasi" value="<?= e($_GET['lokasi'] ?? '') ?>">
        <select name="tipe">
            <option value="">Semua tipe</option><?php foreach (['Rumah', 'Ruko', 'Tanah', 'Apartemen'] as $t): ?><option <?= ($_GET['tipe'] ?? '') == $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">Semua status</option><?php foreach (['Tersedia', 'Dalam Negosiasi', 'Terjual'] as $t): ?><option <?= ($_GET['status'] ?? '') == $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
        </select>
        <input type="number" name="min" placeholder="Harga min" value="<?= e($_GET['min'] ?? '') ?>"><input type="number" name="max" placeholder="Harga maks" value="<?= e($_GET['max'] ?? '') ?>">
        <button>Cari</button>
    </div>
</form>
<div class="grid"><?php foreach ($rows as $r): ?><a class="card" href="detail.php?id=<?= $r['id_properti'] ?>">
            <div class="ph" <?= $r['foto'] ? 'style="background-image:url(uploads/' . e($r['foto']) . ')"' : '' ?>><?= $r['foto'] ? '' : 'Belum ada foto' ?></div>
            <div class="b"><span class="tag <?= strtok($r['status'], ' ') ?>"><?= e($r['status']) ?></span> <span class="tag"><?= e($r['tipe']) ?></span>
                <h3 style="margin:8px 0 4px;color:#1f2933"><?= e($r['judul']) ?></h3>
                <div class="mut"><?= e($r['lokasi']) ?> · LT <?= $r['luas_tanah'] ?> m² · LB <?= $r['luas_bangunan'] ?> m²</div>
                <div class="price"><?= rp($r['harga']) ?></div>
            </div>
        </a>
    <?php endforeach;
                    if (!$rows) echo '<p class="mut">Tidak ada properti yang cocok.</p>'; ?></div>
<?php require 'includes/footer.php';
