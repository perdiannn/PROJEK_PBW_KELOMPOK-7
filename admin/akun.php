<?php require '../config.php';
need(['admin']);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $a = $_POST['a'] ?? '';
    if ($a == 'tambah') {
        $tel = trim($_POST['telepon'] ?? '');
        if (strlen($_POST['password']) < 6) $msg = 'Password minimal 6 karakter.';
        elseif (!preg_match('/^[0-9+\-\s]{8,20}$/', $tel)) $msg = 'Nomor telepon tidak valid.';
        else {
            try {
                $pdo->prepare('INSERT INTO users(nama,email,password,role,telepon) VALUES(?,?,?,"agen",?)')->execute([trim($_POST['nama']), trim($_POST['email']), password_hash($_POST['password'], PASSWORD_DEFAULT), $tel]);
                $msg = 'Akun agen dibuat.';
            } catch (PDOException $x) {
                $msg = 'Email sudah terdaftar.';
            }
        }
    }
    if ($a == 'status') {
        // Nonaktifkan / aktifkan agen. Agen nonaktif tidak bisa login dan tidak bisa ditugaskan ke properti.
        $to = ($_POST['ke'] ?? '') === 'Aktif' ? 'Aktif' : 'Nonaktif';
        $pdo->prepare('UPDATE users SET status_akun=? WHERE id_user=? AND role="agen"')->execute([$to, (int)$_POST['id']]);
        $msg = $to == 'Aktif' ? 'Agen diaktifkan.' : 'Agen dinonaktifkan. Pindahkan propertinya ke agen lain di Kelola Properti.';
    }
    if ($a == 'hapus') {
        try {
            $pdo->prepare('DELETE FROM users WHERE id_user=? AND role="agen"')->execute([(int)$_POST['id']]);
            $msg = 'Akun agen dihapus.';
        } catch (PDOException $x) {
            $msg = 'Gagal: agen masih punya properti, pengajuan, atau riwayat percakapan. Gunakan Nonaktifkan.';
        }
    }
}
$l = $pdo->query('SELECT u.*,
    (SELECT COUNT(*) FROM properti p WHERE p.id_agen=u.id_user) n
    FROM users u WHERE u.role IN("admin","agen") ORDER BY u.role,u.nama');
$title = 'Akun Agen';
require '../includes/header.php'; ?>
<p><a href="index.php">&larr; Dashboard</a></p>
<h2>Kelola Akun Agen</h2><?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<form class="box f" method="post"><?= csrf() ?><input type="hidden" name="a" value="tambah">
    <div class="row"><input name="nama" placeholder="Nama agen" required><input type="email" name="email" placeholder="Email" required><input name="telepon" placeholder="No. telepon / WhatsApp" required><input type="password" name="password" placeholder="Password awal" required><button>Tambah agen</button></div>
</form>
<table>
    <tr>
        <th>Nama</th>
        <th>Email</th>
        <th>Telepon</th>
        <th>Peran</th>
        <th>Status</th>
        <th>Properti</th>
        <th></th>
    </tr><?php foreach ($l as $x): ?><tr>
            <td><?= e($x['nama']) ?></td>
            <td><?= e($x['email']) ?></td>
            <td><?= e($x['telepon'] ?? '-') ?></td>
            <td><?= e($x['role']) ?></td>
            <td><?= $x['role'] == 'agen' ? '<span class="tag ' . ($x['status_akun'] == 'Aktif' ? 'Tersedia' : 'Terjual') . '">' . e($x['status_akun']) . '</span>' : '-' ?></td>
            <td><?= (int)$x['n'] ?></td>
            <td>
                <?php if ($x['role'] == 'agen'): ?>
                    <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="a" value="status"><input type="hidden" name="id" value="<?= (int)$x['id_user'] ?>"><input type="hidden" name="ke" value="<?= $x['status_akun'] == 'Aktif' ? 'Nonaktif' : 'Aktif' ?>"><button class="sec"><?= $x['status_akun'] == 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                    <form method="post" style="display:inline" onsubmit="return confirm('Hapus akun ini?')"><?= csrf() ?><input type="hidden" name="a" value="hapus"><input type="hidden" name="id" value="<?= (int)$x['id_user'] ?>"><button class="del">Hapus</button></form>
                <?php endif; ?>
            </td>
        </tr><?php endforeach; ?>
</table>
<?php require '../includes/footer.php';