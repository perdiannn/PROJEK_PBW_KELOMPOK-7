<?php
require '../config.php';
// Admin HANYA mengelola data properti (agen mengelola pengajuan & survei).
need(['admin']);
$msg = '';
$err = '';
$STATUS = ['Tersedia', 'Dalam Negosiasi', 'Terjual'];

// Agen aktif yang boleh ditugaskan (+ agen yang sedang memegang properti saat edit)
function daftar_agen(PDO $pdo, int $termasuk = 0): array
{
    $s = $pdo->prepare(
        'SELECT id_user, nama, status_akun AS status FROM users
        WHERE role = "agen" AND (status_akun = "Aktif" OR id_user = ?) ORDER BY nama'
    );
    $s->execute([$termasuk]);
    return $s->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($a === 'hapus') {
        try {
            $fs = $pdo->prepare('SELECT nama_file FROM foto_properti WHERE id_properti = ?');
            $fs->execute([$id]);
            $fs = $fs->fetchAll(PDO::FETCH_COLUMN);
            $pdo->prepare('DELETE FROM properti WHERE id_properti = ?')->execute([$id]);
            foreach ($fs as $x) {
                @unlink('../uploads/' . $x);
            }
            $msg = 'Properti dihapus.';
        } catch (PDOException $x) {
            $err = 'Gagal: properti sudah punya transaksi.';
        }
    }

    if ($a === 'simpan') {
        $tipe = $_POST['tipe'] ?? '';
        $status = $_POST['status'] ?? '';
        $judul = trim($_POST['judul'] ?? '');
        $lokasi = trim($_POST['lokasi'] ?? '');
        $harga = (int) ($_POST['harga'] ?? 0);
        $idAgen = (int) ($_POST['id_agen'] ?? 0);
        $sert = $_POST['sertifikat'] ?? '';
        $cfg = TIPE_PROPERTI[$tipe] ?? null;

        // Agen harus aktif, kecuali tidak diganti saat edit
        $cekAgen = $pdo->prepare('SELECT status_akun FROM users WHERE id_user = ? AND role = "agen"');
        $cekAgen->execute([$idAgen]);
        $stAgen = $cekAgen->fetchColumn();
        $agenLama = 0;
        if ($id) {
            $q = $pdo->prepare('SELECT id_agen FROM properti WHERE id_properti = ?');
            $q->execute([$id]);
            $agenLama = (int) $q->fetchColumn();
        }

        if (!$cfg) $err = 'Tipe properti tidak valid.';
        elseif ($judul === '' || $lokasi === '') $err = 'Judul dan lokasi wajib diisi.';
        elseif ($harga <= 0) $err = 'Harga harus lebih dari 0.';
        elseif (!in_array($status, $STATUS, true)) $err = 'Status tidak valid.';
        elseif (!$stAgen || ($stAgen !== 'Aktif' && $idAgen !== $agenLama)) $err = 'Pilih agen yang aktif.';

        if (!$err) {
            $lt = $cfg['luas_tanah'] && ($_POST['lt'] ?? '') !== '' ? max(0, (int) $_POST['lt']) : null;
            $lb = $cfg['luas_bangunan'] && ($_POST['lb'] ?? '') !== '' ? max(0, (int) $_POST['lb']) : null;
            $sert = in_array($sert, SERTIFIKAT, true) ? $sert : null;
            $spek = spek_bersihkan($tipe, $_POST);
            $d = [$judul, trim($_POST['deskripsi'] ?? ''), $harga, $lokasi, $tipe, $lt, $lb, $sert, $status, $idAgen];

            $pdo->beginTransaction();
            try {
                if ($id) {
                    $pdo->prepare(
                        'UPDATE properti SET judul=?, deskripsi=?, harga=?, lokasi=?, tipe=?, luas_tanah=?, luas_bangunan=?, sertifikat=?, status=?, id_agen=?
                        WHERE id_properti=?'
                    )->execute([...$d, $id]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO properti (judul, deskripsi, harga, lokasi, tipe, luas_tanah, luas_bangunan, sertifikat, status, id_agen)
                        VALUES (?,?,?,?,?,?,?,?,?,?)'
                    )->execute($d);
                    $id = (int) $pdo->lastInsertId();
                }
                // Atribut khusus tipe: tulis ulang seluruhnya
                $pdo->prepare('DELETE FROM properti_spesifikasi WHERE id_properti = ?')->execute([$id]);
                $ins = $pdo->prepare('INSERT INTO properti_spesifikasi (id_properti, kunci, nilai) VALUES (?,?,?)');
                foreach ($spek as $k => $v) $ins->execute([$id, $k, $v]);
                $pdo->commit();
            } catch (Throwable $x) {
                $pdo->rollBack();
                throw $x;
            }

            // Upload foto
            foreach ($_FILES['foto']['tmp_name'] ?? [] as $i => $t) {
                if (!$t || $_FILES['foto']['error'][$i] || $_FILES['foto']['size'][$i] > 2097152) continue;
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][(new finfo(FILEINFO_MIME_TYPE))->file($t)] ?? null;
                if (!$ext) continue;
                $n = bin2hex(random_bytes(8)) . '.' . $ext;
                move_uploaded_file($t, '../uploads/' . $n);
                $pdo->prepare('INSERT INTO foto_properti (id_properti, nama_file) VALUES (?,?)')->execute([$id, $n]);
            }
            $msg = 'Properti disimpan.';
        }
    }
}

// Data form (default / edit)
$e = [
    'judul' => '',
    'deskripsi' => '',
    'harga' => '',
    'lokasi' => '',
    'tipe' => 'Rumah',
    'luas_tanah' => '',
    'luas_bangunan' => '',
    'sertifikat' => '',
    'status' => 'Tersedia',
    'id_agen' => 0,
    'id_properti' => 0,
];
$spekE = [];
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM properti WHERE id_properti = ?');
    $s->execute([(int) $_GET['edit']]);
    if ($row = $s->fetch()) {
        $e = $row;
        $spekE = spek_ambil($pdo, (int) $row['id_properti']);
    }
} elseif ($err) {
    // simpan gagal: pertahankan isian
    $e = array_merge($e, [
        'judul' => $_POST['judul'] ?? '',
        'deskripsi' => $_POST['deskripsi'] ?? '',
        'harga' => $_POST['harga'] ?? '',
        'lokasi' => $_POST['lokasi'] ?? '',
        'tipe' => $_POST['tipe'] ?? 'Rumah',
        'luas_tanah' => $_POST['lt'] ?? '',
        'luas_bangunan' => $_POST['lb'] ?? '',
        'sertifikat' => $_POST['sertifikat'] ?? '',
        'status' => $_POST['status'] ?? 'Tersedia',
        'id_agen' => (int) ($_POST['id_agen'] ?? 0),
        'id_properti' => (int) ($_POST['id'] ?? 0),
    ]);
    $spekE = $_POST['spek'] ?? [];
}
$agenOpsi = daftar_agen($pdo, (int) $e['id_agen']);

$l = $pdo->query(
    'SELECT p.*, u.nama AS nama_agen, u.status_akun AS status_agen
    FROM properti p JOIN users u ON u.id_user = p.id_agen
    ORDER BY p.created_at DESC'
);

$title = 'Kelola Properti';
require '../includes/header.php';
?>
<p><a href="index.php">&larr; Dashboard</a></p>
<h2>Kelola Properti</h2>
<?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>

<form class="box f" method="post" enctype="multipart/form-data" id="formProperti">
    <?= csrf() ?>
    <input type="hidden" name="a" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $e['id_properti'] ?>">
    <h3 style="margin:0"><?= $e['id_properti'] ? 'Edit' : 'Tambah' ?> Properti</h3>

    <input name="judul" placeholder="Judul" value="<?= e($e['judul']) ?>" required>

    <div class="row">
        <div><label>Tipe properti</label>
            <select name="tipe" id="tipe">
                <?php foreach (array_keys(TIPE_PROPERTI) as $t): ?>
                    <option <?= $e['tipe'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>Harga (Rp)</label><input type="number" name="harga" min="1" value="<?= e($e['harga']) ?>" required></div>
        <div><label>Lokasi</label><input name="lokasi" value="<?= e($e['lokasi']) ?>" required></div>
    </div>

    <div class="row">
        <div><label>Agen penanggung jawab</label>
            <select name="id_agen" required>
                <option value="">- Pilih Agen -</option>
                <?php foreach ($agenOpsi as $g): ?>
                    <option value="<?= (int) $g['id_user'] ?>" <?= (int) $e['id_agen'] === (int) $g['id_user'] ? 'selected' : '' ?>>
                        <?= e($g['nama']) ?><?= $g['status'] !== 'Aktif' ? ' (nonaktif)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>Status</label>
            <select name="status">
                <?php foreach ($STATUS as $t): ?>
                    <option <?= $e['status'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>Sertifikat</label>
            <select name="sertifikat">
                <option value="">-</option>
                <?php foreach (SERTIFIKAT as $t): ?>
                    <option <?= $e['sertifikat'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div id="boxLt"><label>Luas tanah (m²)</label><input type="number" min="0" name="lt" value="<?= e($e['luas_tanah']) ?>"></div>
        <div id="boxLb"><label id="lblLb">Luas bangunan (m²)</label><input type="number" min="0" name="lb" value="<?= e($e['luas_bangunan']) ?>"></div>
    </div>

    <?php foreach (TIPE_PROPERTI as $tp => $cfg): ?>
        <div class="spek row" data-tipe="<?= e($tp) ?>" hidden>
            <?php foreach ($cfg['spek'] as $k => [$label, $jenis, $satuan, $opsi]): $val = (string) ($spekE[$k] ?? ''); ?>
                <div><label><?= e($label) ?><?= $satuan ? ' (' . e($satuan) . ')' : '' ?></label>
                    <?php if ($jenis === 'select'): ?>
                        <select name="spek[<?= e($k) ?>]">
                            <option value="">-</option>
                            <?php foreach ($opsi as $o): ?><option <?= $val === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="<?= $jenis === 'number' ? 'number' : 'text' ?>" <?= $jenis === 'number' ? 'min="0"' : 'maxlength="100"' ?> name="spek[<?= e($k) ?>]" value="<?= e($val) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <textarea name="deskripsi" rows="12" placeholder="Deskripsi"><?= e($e['deskripsi']) ?></textarea>
    <div>
        <label>Foto (JPG/PNG/WEBP, maks 2 MB per file)</label>
        <input type="file" name="foto[]" multiple accept="image/*">
    </div>
    <button>Simpan</button>
</form>

<script>
    // Tampilkan hanya isian yang relevan untuk tipe terpilih. Isian yang disembunyikan di-disable
    // supaya tidak ikut terkirim (kunci yang sama, mis. kamar_tidur, ada di beberapa tipe).
    const CFG = <?= json_encode(array_map(fn($c) => ['lt' => $c['luas_tanah'], 'lb' => $c['luas_bangunan']], TIPE_PROPERTI)) ?>;
    const tipe = document.getElementById('tipe');

    function terapkan() {
        const t = tipe.value,
            c = CFG[t];
        document.querySelectorAll('.spek').forEach(el => {
            const aktif = el.dataset.tipe === t;
            el.hidden = !aktif;
            el.querySelectorAll('input,select').forEach(i => i.disabled = !aktif);
        });
        [
            ['boxLt', c.lt],
            ['boxLb', c.lb]
        ].forEach(([id, on]) => {
            const b = document.getElementById(id);
            b.hidden = !on;
            b.querySelector('input').disabled = !on;
        });
        document.getElementById('lblLb').textContent = t === 'Apartemen' ? 'Luas unit (m²)' : 'Luas bangunan (m²)';
    }
    tipe.addEventListener('change', terapkan);
    terapkan();
</script>

<table>
    <tr>
        <th>Judul</th>
        <th>Tipe</th>
        <th>Harga</th>
        <th>Agen</th>
        <th>Status</th>
        <th></th>
    </tr>
    <?php foreach ($l as $r): ?>
        <tr>
            <td><?= e($r['judul']) ?></td>
            <td><?= e($r['tipe']) ?></td>
            <td><?= rp($r['harga']) ?></td>
            <td><?= e($r['nama_agen']) ?><?= $r['status_agen'] !== 'Aktif' ? ' <span class="tag Terjual">nonaktif</span>' : '' ?></td>
            <td><span class="tag <?= e(strtok($r['status'], ' ')) ?>"><?= e($r['status']) ?></span></td>
            <td>
                <a class="btn sec" href="?edit=<?= (int) $r['id_properti'] ?>">Edit</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus properti ini?')">
                    <?= csrf() ?>
                    <input type="hidden" name="a" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id_properti'] ?>">
                    <button class="del">Hapus</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require '../includes/footer.php'; ?>