<?php require 'config.php';
require 'includes/negosiasi.php';
$u = user();
$id = (int)($_GET['id'] ?? 0);
$msg = '';
$err = '';
$s = $pdo->prepare('SELECT p.*, us.nama agen, us.telepon telepon_agen FROM properti p JOIN users us ON us.id_user=p.id_agen WHERE p.id_properti=?');
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
    $pesan = trim($_POST['pesan'] ?? '');
    $ada = $pdo->prepare('SELECT id_permintaan FROM permintaan WHERE id_user=? AND id_properti=? AND status NOT IN ("Selesai","Dibatalkan") LIMIT 1');
    $ada->execute([$u['id'], $id]);
    if ($r['status'] == 'Terjual') {
        $err = 'Properti ini sudah terjual.';
    } elseif ($pesan === '') {
        $err = 'Pesan wajib diisi.';
    } elseif ($idAda = $ada->fetchColumn()) {
        flash('Anda sudah punya pengajuan aktif untuk properti ini. Lanjutkan di sini.');
        header('Location: pengajuan.php?id=' . $idAda);
        exit;
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO permintaan(id_user,id_properti,pesan) VALUES(?,?,?)')->execute([$u['id'], $id, $pesan]);
            $nid = (int)$pdo->lastInsertId();
            // Usulan awal dari pembeli (opsional): jadwal survei & harga tawaran, menunggu persetujuan agen
            if (!empty($_POST['tgl'])) neg_buat($pdo, $nid, (int)$u['id'], 'Jadwal');
            if (!empty($_POST['harga'])) {
                neg_buat($pdo, $nid, (int)$u['id'], 'Harga');
                $pdo->prepare('UPDATE permintaan SET status="Negosiasi Harga" WHERE id_permintaan=?')->execute([$nid]);
            }
            $pdo->commit();
            flash('Permintaan terkirim. Agen akan menanggapi usulan Anda di sini.');
            header('Location: pengajuan.php?id=' . $nid);
            exit;
        } catch (DomainException $x) {
            $pdo->rollBack();
            $err = $x->getMessage();
        } catch (Throwable $x) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $x;
        }
    }
}
$f = $pdo->prepare('SELECT nama_file FROM foto_properti WHERE id_properti=? ORDER BY id_foto');
$f->execute([$id]);
$fotos = $f->fetchAll(PDO::FETCH_COLUMN);

// Informasi detail disesuaikan dengan tipe properti
$info = [];
$c = TIPE_PROPERTI[$r['tipe']] ?? ['luas_tanah' => true, 'luas_bangunan' => true];
if ($c['luas_tanah'] && $r['luas_tanah'] !== null) $info[] = [label_luas($r['tipe'], 'luas_tanah'), $r['luas_tanah'] . ' m²'];
if ($c['luas_bangunan'] && $r['luas_bangunan'] !== null) $info[] = [label_luas($r['tipe'], 'luas_bangunan'), $r['luas_bangunan'] . ' m²'];
if ($r['sertifikat']) $info[] = ['Sertifikat', $r['sertifikat']];
foreach (spek_tampil($r['tipe'], spek_ambil($pdo, $id)) as $x) $info[] = $x;

$title = $r['judul'];
require 'includes/header.php'; ?>
<?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
<?php tampil_flash(); ?>
<div class="card" style="padding:20px">
    <h2 style="margin-top:0"><?= e($r['judul']) ?></h2><span class="tag <?= strtok($r['status'], ' ') ?>"><?= e($r['status']) ?></span> <span class="tag"><?= e($r['tipe']) ?></span>
    <p class="price"><?= rp($r['harga']) ?></p>
    <p class="mut"><?= e($r['lokasi']) ?> · Agen: <?= e($r['agen']) ?><?= $r['telepon_agen'] && $r['telepon_agen'] !== '-' ? ' (' . e($r['telepon_agen']) . ')' : '' ?></p>
    <div class="gal"><?php foreach ($fotos as $x): ?><img src="uploads/<?= e($x) ?>" alt=""><?php endforeach;
        if (!$fotos) echo '<div class="ph" style="grid-column:1/-1">Belum ada foto</div>'; ?></div>
    <?php if ($info): ?>
        <table style="margin-top:16px">
            <?php foreach (array_chunk($info, 2) as $pair): ?>
                <tr>
                    <?php foreach ($pair as [$lb, $val]): ?><th><?= e($lb) ?></th>
                        <td><?= e($val) ?></td><?php endforeach; ?>
                    <?php if (count($pair) < 2): ?><th></th>
                        <td></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    <p><?= nl2br(e($r['deskripsi'])) ?></p>
</div>
<?php if ($r['status'] != 'Terjual'): ?><div class="box">
        <h3 style="margin-top:0">Tertarik? Hubungi agen</h3>
        <?php if ($u && $u['role'] == 'pembeli'): ?>
            <form class="f" method="post"><?= csrf() ?>
                <textarea name="pesan" rows="3" required placeholder="Tulis pesan untuk agen"></textarea>
                <div class="row">
                    <div><label>Usulan jadwal survei (opsional)</label><input type="datetime-local" name="tgl" min="<?= date('Y-m-d\TH:i') ?>"></div>
                    <div><label>Tawaran harga (opsional, Rp)</label><input type="number" name="harga" min="1" step="1"></div>
                </div>
                <p class="mut" style="margin:0">Jadwal dan harga baru berlaku setelah disetujui agen. Agen juga bisa mengajukan usulan tandingan.</p>
                <button>Kirim</button>
            </form>
        <?php else: ?><p class="mut"><a href="login.php">Masuk</a> sebagai pembeli untuk menghubungi agen.</p><?php endif; ?>
    </div><?php endif; ?>
<?php require 'includes/footer.php';
