<?php require 'config.php';
$u = user();
$id = (int)($_GET['id'] ?? 0);
$msg = '';
$s = $pdo->prepare('SELECT p.*,u.nama agen FROM properti p JOIN users u ON u.id_user=p.id_agen WHERE id_properti=?');
$s->execute([$id]);
$r = $s->fetch();
if (!$r) {
    http_response_code(404);
    die('Properti tidak ditemukan');
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$u || $u['role'] != 'pembeli') {
        header('Location: login.php');
        exit;
    }
    $pdo->prepare('INSERT INTO permintaan(id_user,id_properti,pesan) VALUES(?,?,?)')->execute([$u['id'], $id, trim($_POST['pesan'])]);
    $msg = 'Permintaan terkirim. Pantau di menu Pengajuan Saya.';
}
$f = $pdo->prepare('SELECT nama_file FROM foto_properti WHERE id_properti=? ORDER BY id_foto');
$f->execute([$id]);
$fotos = $f->fetchAll(PDO::FETCH_COLUMN);
$title = $r['judul'];
require 'includes/header.php'; ?>
<?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="card" style="padding:20px">
    <h2 style="margin-top:0"><?= e($r['judul']) ?></h2><span class="tag <?= strtok($r['status'], ' ') ?>"><?= e($r['status']) ?></span> <span class="tag"><?= e($r['tipe']) ?></span>
    <p class="price"><?= rp($r['harga']) ?></p>
    <p class="mut"><?= e($r['lokasi']) ?> · Agen: <?= e($r['agen']) ?></p>
    <div class="gal"><?php foreach ($fotos as $x): ?><img src="uploads/<?= e($x) ?>" alt=""><?php endforeach;
                                                                                    if (!$fotos) echo '<div class="ph" style="grid-column:1/-1">Belum ada foto</div>'; ?></div>
    <table style="margin-top:16px">
        <tr>
            <th>Luas tanah</th>
            <td><?= $r['luas_tanah'] ?> m²</td>
            <th>Luas bangunan</th>
            <td><?= $r['luas_bangunan'] ?> m²</td>
        </tr>
        <tr>
            <th>Kamar tidur</th>
            <td><?= $r['kamar_tidur'] ?></td>
            <th>Kamar mandi</th>
            <td><?= $r['kamar_mandi'] ?></td>
        </tr>
    </table>
    <p><?= nl2br(e($r['deskripsi'])) ?></p>
</div>
<?php if ($r['status'] != 'Terjual'): ?><div class="box">
        <h3 style="margin-top:0">Tertarik? Kirim permintaan informasi / survei</h3>
        <?php if ($u && $u['role'] == 'pembeli'): ?><form class="f" method="post"><?= csrf() ?><textarea name="pesan" rows="3" required placeholder="Tulis pesan atau usulan tanggal survei"></textarea><button>Kirim</button></form>
        <?php else: ?><p class="mut"><a href="login.php">Masuk</a> sebagai pembeli untuk mengirim permintaan.</p><?php endif; ?>
    </div><?php endif; ?>
<?php require 'includes/footer.php';
