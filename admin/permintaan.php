<?php
require '../config.php';
// Memeriksa hak akses pengguna
need(['admin', 'agen']);

// Mengambil informasi pengguna
$u = user();
$adm = ($u['role'] === 'admin');
$me = $u['id'];

// Mengambil ID permintaan dari URL
$id = (int) ($_GET['id'] ?? 0);

// Query utama untuk mengambil data permintaan
$q = '
    SELECT
        r.*,
        u.nama,
        u.email,
        p.judul,
        p.id_properti,
        p.harga
    FROM permintaan r
    JOIN users u ON u.id_user = r.id_user
    JOIN properti p USING (id_properti)
    WHERE (? = 1 OR p.id_agen = ?)
';

// Jika ID tersedia, tampilkan detail permintaan
if ($id) {
    $s = $pdo->prepare($q . ' AND r.id_permintaan = ?');
    $s->execute([
        $adm ? 1 : 0,
        $me,
        $id
    ]);
    $r = $s->fetch();
    // Memeriksa apakah permintaan ditemukan
    if (!$r) {
        die('Akses ditolak');
    }
    // Memproses form yang dikirim
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $a = $_POST['a'];
        // Mengubah status permintaan
        if ($a === 'status') {
            $pdo->prepare(
                'UPDATE permintaan
                SET status = ?
                WHERE id_permintaan = ?'
            )->execute([
                $_POST['status'],
                $id
            ]);
        }
        // Menambahkan jadwal survei
        if ($a === 'survei') {
            $pdo->prepare(
                'INSERT INTO jadwal_survei
                (id_permintaan, tanggal_survei)
                VALUES (?, ?)'
            )->execute([
                $id,
                $_POST['tgl']
            ]);
            // Memperbarui status permintaan
            $pdo->prepare(
                'UPDATE permintaan
                SET status = "Survei Dijadwalkan"
                WHERE id_permintaan = ?'
            )->execute([$id]);
        }
        // Mengubah status jadwal survei
        if ($a === 'survei_status') {
            $pdo->prepare(
                'UPDATE jadwal_survei
                SET status_survei = ?
                WHERE id_survei = ?
                AND id_permintaan = ?'
            )->execute([
                $_POST['ss'],
                (int) $_POST['sid'],
                $id
            ]);
        }
        // Menambahkan catatan tindak lanjut
        if ($a === 'catatan') {
            $pdo->prepare(
                'INSERT INTO tindak_lanjut
                (id_permintaan, id_agen, catatan)
                VALUES (?, ?, ?)'
            )->execute([
                $id,
                $me,
                trim($_POST['catatan'])
            ]);
        }
        // Mencatat transaksi penjualan
        if ($a === 'transaksi') {
            $pdo->beginTransaction();
            try {
                // Menambahkan data transaksi
                $pdo->prepare(
                    'INSERT INTO transaksi_penjualan
                    (id_properti, id_pembeli, tanggal_transaksi, harga_final)
                    VALUES (?, ?, CURDATE(), ?)'
                )->execute([
                    $r['id_properti'],
                    $r['id_user'],
                    (int) $_POST['final']
                ]);
                // Mengubah status properti menjadi Terjual
                $pdo->prepare(
                    'UPDATE properti
                    SET status = "Terjual"
                    WHERE id_properti = ?'
                )->execute([
                    $r['id_properti']
                ]);
                // Menyelesaikan permintaan pembeli
                $pdo->prepare(
                    'UPDATE permintaan
                    SET status = "Selesai"
                    WHERE id_permintaan = ?'
                )->execute([$id]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        // Mengarahkan kembali ke halaman detail
        header('Location: permintaan.php?id=' . $id);
        exit;
    }
    // Mengambil data jadwal survei
    $sv = $pdo->prepare(
        'SELECT *
        FROM jadwal_survei
        WHERE id_permintaan = ?
        ORDER BY tanggal_survei'
    );
    $sv->execute([$id]);
    // Mengambil riwayat tindak lanjut
    $tl = $pdo->prepare(
        'SELECT
            t.*,
            u.nama
        FROM tindak_lanjut t
        JOIN users u ON u.id_user = t.id_agen
        WHERE id_permintaan = ?
        ORDER BY tanggal DESC'
    );
    $tl->execute([$id]);
} else {
    // Mengambil seluruh daftar permintaan
    $s = $pdo->prepare(
        $q . ' ORDER BY r.tanggal DESC'
    );
    $s->execute([
        $adm ? 1 : 0,
        $me
    ]);
}
// Menentukan judul halaman
$title = 'Prospek';
require '../includes/header.php';
?>

<!-- HALAMAN DETAIL PERMINTAAN -->
<?php if ($id): ?>
    <p>
        <a href="permintaan.php">&larr; Kembali</a>
    </p>
    <h2><?= e($r['judul']) ?></h2>
    <!-- Informasi Calon Pembeli -->
    <div class="box">
        <b><?= e($r['nama']) ?></b>
        · <?= e($r['email']) ?>
        <p>
            <?= nl2br(e($r['pesan'])) ?>
        </p>
        <!-- Form Perubahan Status -->
        <form class="row" method="post">
            <?= csrf() ?>
            <input
                type="hidden"
                name="a"
                value="status">
            <select name="status">
                <?php foreach (
                    ['Diajukan', 'Dihubungi', 'Survei Dijadwalkan', 'Selesai']
                    as $t
                ): ?>
                    <option
                        <?= $r['status'] === $t ? 'selected' : '' ?>>
                        <?= $t ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button>Ubah status</button>
        </form>
    </div>

    <!-- JADWAL SURVEI -->
    <div class="box">
        <h3 style="margin-top: 0;">
            Jadwal Survei
        </h3>
        <!-- Form Tambah Jadwal -->
        <form class="row" method="post">
            <?= csrf() ?>
            <input
                type="hidden"
                name="a"
                value="survei">
            <input
                type="datetime-local"
                name="tgl"
                required>
            <button>Jadwalkan</button>
        </form>
        <!-- Daftar Jadwal Survei -->
        <table>
            <?php foreach ($sv as $x): ?>
                <tr>
                    <td>
                        <?= e($x['tanggal_survei']) ?>
                    </td>
                    <td>
                        <form class="row" method="post">
                            <?= csrf() ?>
                            <input
                                type="hidden"
                                name="a"
                                value="survei_status">
                            <input
                                type="hidden"
                                name="sid"
                                value="<?= $x['id_survei'] ?>">
                            <select name="ss">
                                <?php foreach (
                                    ['Menunggu', 'Dikonfirmasi', 'Selesai', 'Dibatalkan']
                                    as $t
                                ): ?>
                                    <option
                                        <?= $x['status_survei'] === $t ? 'selected' : '' ?>>
                                        <?= $t ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="sec">
                                Simpan
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- TINDAK LANJUT -->
    <div class="box">
        <h3 style="margin-top: 0;">
            Tindak Lanjut
        </h3>
        <!-- Form Tambah Catatan -->
        <form class="f" method="post">
            <?= csrf() ?>
            <input
                type="hidden"
                name="a"
                value="catatan">
            <textarea
                name="catatan"
                rows="2"
                required
                placeholder="Catatan komunikasi">
            </textarea>
            <button>Tambah catatan</button>
        </form>
        <!-- Riwayat Catatan -->
        <?php foreach ($tl as $x): ?>
            <p class="mut">
                <?= e($x['tanggal']) ?>
                · <?= e($x['nama']) ?>
                <br>
                <span style="color: #1f2933;">
                    <?= nl2br(e($x['catatan'])) ?>
                </span>
            </p>
        <?php endforeach; ?>
    </div>

    <!-- TRANSAKSI PENJUALAN -->
    <div class="box">
        <h3 style="margin-top: 0;">
            Catat Penjualan
        </h3>
        <form
            class="row"
            method="post"
            onsubmit="return confirm('Tandai properti Terjual?')">
            <?= csrf() ?>
            <input
                type="hidden"
                name="a"
                value="transaksi">
            <input
                type="number"
                name="final"
                value="<?= $r['harga'] ?>"
                required>
            <button>Simpan transaksi</button>
        </form>
    </div>

    <!-- HALAMAN DAFTAR PERMINTAAN -->
<?php else: ?>
    <h2>Prospek &amp; Survei</h2>
    <table>
        <tr>
            <th>Tanggal</th>
            <th>Calon pembeli</th>
            <th>Properti</th>
            <th>Status</th>
            <th></th>
        </tr>
        <?php foreach ($s as $x): ?>
            <tr>
                <td>
                    <?= e($x['tanggal']) ?>
                </td>
                <td>
                    <?= e($x['nama']) ?>
                </td>
                <td>
                    <?= e($x['judul']) ?>
                </td>
                <td>
                    <span class="tag">
                        <?= e($x['status']) ?>
                    </span>
                </td>
                <td>
                    <a
                        class="btn sec"
                        href="?id=<?= $x['id_permintaan'] ?>">
                        Kelola
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<?php require '../includes/footer.php'; ?>