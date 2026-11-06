<?php
require '../config.php';
// Memeriksa hak akses pengguna
need(['admin', 'agen']);
// Mengambil informasi pengguna
$u = user();
$adm = ($u['role'] === 'admin');
$me = $u['id'];
// Variabel pesan notifikasi
$msg = '';
// PROSES FORM (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mengambil aksi dan ID properti
    $a = $_POST['a'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    // Memeriksa kepemilikan properti
    if ($id) {
        $c = $pdo->prepare(
            'SELECT id_agen
            FROM properti
            WHERE id_properti = ?'
        );
        $c->execute([$id]);
        $o = $c->fetchColumn();
        if (!$o || (!$adm && $o != $me)) {
            die('Akses ditolak');
        }
    }
    // HAPUS PROPERTI 
    if ($a === 'hapus') {
        try {
            // Mengambil daftar foto properti
            $fs = $pdo->prepare(
                'SELECT nama_file
                FROM foto_properti
                WHERE id_properti = ?'
            );
            $fs->execute([$id]);
            $fs = $fs->fetchAll(PDO::FETCH_COLUMN);
            // Menghapus data properti dari database
            $pdo->prepare(
                'DELETE FROM properti
                WHERE id_properti = ?'
            )->execute([$id]);
            // Menghapus file foto dari folder uploads
            foreach ($fs as $x) {
                @unlink('../uploads/' . $x);
            }
            $msg = 'Properti dihapus.';
        } catch (PDOException $x) {
            $msg = 'Gagal: properti sudah punya transaksi.';
        }
    }
    // TAMBAH DAN EDIT PROPERTI 
    if ($a === 'simpan') {
        // Mengambil data dari form
        $d = [
            $_POST['judul'],
            $_POST['deskripsi'],
            (int) $_POST['harga'],
            $_POST['lokasi'],
            $_POST['tipe'],
            (int) $_POST['lt'],
            (int) $_POST['lb'],
            (int) $_POST['kt'],
            (int) $_POST['km'],
            $_POST['status']
        ];
        // Jika ID tersedia, lakukan UPDATE
        if ($id) {
            $pdo->prepare(
                'UPDATE properti SET
                    judul = ?,
                    deskripsi = ?,
                    harga = ?,
                    lokasi = ?,
                    tipe = ?,
                    luas_tanah = ?,
                    luas_bangunan = ?,
                    kamar_tidur = ?,
                    kamar_mandi = ?,
                    status = ?
                WHERE id_properti = ?'
            )->execute([
                ...$d,
                $id
            ]);
        } else {
            // Jika ID tidak tersedia, lakukan INSERT
            $pdo->prepare(
                'INSERT INTO properti (
                    judul,
                    deskripsi,
                    harga,
                    lokasi,
                    tipe,
                    luas_tanah,
                    luas_bangunan,
                    kamar_tidur,
                    kamar_mandi,
                    status,
                    id_agen
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                ...$d,
                $me
            ]);
            // Mengambil ID properti yang baru dibuat
            $id = $pdo->lastInsertId();
        }
        // PROSES UPLOAD FOTO PROPERTI
        foreach ($_FILES['foto']['tmp_name'] ?? [] as $i => $t) {
            // Memeriksa file yang diunggah
            if (
                !$t ||
                $_FILES['foto']['error'][$i] ||
                $_FILES['foto']['size'][$i] > 2097152
            ) {
                continue;
            }
            // Memeriksa tipe MIME file
            $ext = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ][(new finfo(FILEINFO_MIME_TYPE))->file($t)] ?? null;
            // Melewati file dengan format tidak didukung
            if (!$ext) {
                continue;
            }
            // Membuat nama file secara acak
            $n = bin2hex(random_bytes(8)) . '.' . $ext;
            // Memindahkan file ke folder uploads
            move_uploaded_file(
                $t,
                '../uploads/' . $n
            );
            // Menyimpan nama file ke database
            $pdo->prepare(
                'INSERT INTO foto_properti
                (id_properti, nama_file)
                VALUES (?, ?)'
            )->execute([
                $id,
                $n
            ]);
        }
        $msg = 'Properti disimpan.';
    }
}

// DATA DEFAULT FORM

$e = [
    'judul' => '',
    'deskripsi' => '',
    'harga' => '',
    'lokasi' => '',
    'tipe' => 'Rumah',
    'luas_tanah' => 0,
    'luas_bangunan' => 0,
    'kamar_tidur' => 0,
    'kamar_mandi' => 0,
    'status' => 'Tersedia',
    'id_properti' => 0
];

// MENGAMBIL DATA UNTUK EDIT 

if (!empty($_GET['edit'])) {

    $s = $pdo->prepare(
        'SELECT *
        FROM properti
        WHERE id_properti = ?
        AND (? = 1 OR id_agen = ?)'
    );
    $s->execute([
        (int) $_GET['edit'],
        $adm ? 1 : 0,
        $me
    ]);
    $e = $s->fetch() ?: $e;
}

// MENGAMBIL DAFTAR PROPERTI

$l = $pdo->prepare(
    'SELECT *
    FROM properti
    WHERE (? = 1 OR id_agen = ?)
    ORDER BY created_at DESC'
);
$l->execute([
    $adm ? 1 : 0,
    $me
]);

// MEMUAT HEADER HALAMAN
$title = 'Kelola Properti';
require '../includes/header.php';
?>
<!-- HALAMAN KELOLA PROPERTI -->
<h2>Kelola Properti</h2>
<!-- Pesan Notifikasi -->
<?php if ($msg): ?>
    <div class="msg">
        <?= e($msg) ?>
    </div>
<?php endif; ?>

<!-- FORM TAMBAH DAN EDIT PROPERTI -->
<form
    class="box f"
    method="post"
    enctype="multipart/form-data">
    <?= csrf() ?>
    <input
        type="hidden"
        name="a"
        value="simpan">
    <input
        type="hidden"
        name="id"
        value="<?= $e['id_properti'] ?>">
    <h3 style="margin: 0;">
        <?= $e['id_properti'] ? 'Edit' : 'Tambah' ?> Properti
    </h3>
    <!-- Judul Properti -->
    <input
        name="judul"
        placeholder="Judul"
        value="<?= e($e['judul']) ?>"
        required>
    <!-- Informasi Utama Properti -->
    <div class="row">
        <input
            type="number"
            name="harga"
            placeholder="Harga (Rp)"
            value="<?= e($e['harga']) ?>"
            required>
        <input
            name="lokasi"
            placeholder="Lokasi"
            value="<?= e($e['lokasi']) ?>"
            required>
        <!-- Tipe Properti -->
        <select name="tipe">
            <?php foreach (
                ['Rumah', 'Ruko', 'Tanah', 'Apartemen']
                as $t
            ): ?>
                <option
                    <?= $e['tipe'] === $t ? 'selected' : '' ?>>
                    <?= $t ?>
                </option>
            <?php endforeach; ?>
        </select>
        <!-- Status Properti -->
        <select name="status">
            <?php foreach (
                ['Tersedia', 'Dalam Negosiasi', 'Terjual']
                as $t
            ): ?>
                <option
                    <?= $e['status'] === $t ? 'selected' : '' ?>>
                    <?= $t ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <!-- Spesifikasi Properti -->
    <div class="row">
        <div>
            <label>Luas tanah (m²)</label>
            <input
                type="number"
                name="lt"
                value="<?= $e['luas_tanah'] ?>">
        </div>
        <div>
            <label>Luas bangunan (m²)</label>
            <input
                type="number"
                name="lb"
                value="<?= $e['luas_bangunan'] ?>">
        </div>
        <div>
            <label>Kamar tidur</label>
            <input
                type="number"
                name="kt"
                value="<?= $e['kamar_tidur'] ?>">
        </div>
        <div>
            <label>Kamar mandi</label>
            <input
                type="number"
                name="km"
                value="<?= $e['kamar_mandi'] ?>">
        </div>
    </div>
    <!-- Deskripsi Properti -->
    <textarea
        name="deskripsi"
        rows="3"
        placeholder="Deskripsi"><?= e($e['deskripsi']) ?></textarea>
    <!-- Upload Foto -->
    <div>
        <label>
            Foto (JPG/PNG/WEBP, maks 2 MB per file)
        </label>
        <input
            type="file"
            name="foto[]"
            multiple
            accept="image/*">
    </div>
    <!-- Tombol Simpan -->
    <button>Simpan</button>
</form>

<!-- TABEL DAFTAR PROPERTI -->
<table>
    <tr>
        <th>Judul</th>
        <th>Harga</th>
        <th>Status</th>
        <th></th>
    </tr>
    <?php foreach ($l as $r): ?>
        <tr>
            <!-- Nama Properti -->
            <td>
                <?= e($r['judul']) ?>
            </td>
            <!-- Harga Properti -->
            <td>
                <?= rp($r['harga']) ?>
            </td>
            <!-- Status Properti -->
            <td>
                <?= e($r['status']) ?>
            </td>
            <!-- Aksi Edit dan Hapus -->
            <td>
                <a
                    class="btn sec"
                    href="?edit=<?= $r['id_properti'] ?>">
                    Edit
                </a>
                <form
                    method="post"
                    style="display: inline;"
                    onsubmit="return confirm('Hapus properti ini?')">
                    <?= csrf() ?>
                    <input
                        type="hidden"
                        name="a"
                        value="hapus">
                    <input
                        type="hidden"
                        name="id"
                        value="<?= $r['id_properti'] ?>">
                    <button class="del">
                        Hapus
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require '../includes/footer.php'; ?>