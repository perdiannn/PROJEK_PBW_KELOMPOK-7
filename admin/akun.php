<?php require '../config.php';
need(['admin']);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $a = $_POST['a'];
    if ($a == 'tambah') {
        if (strlen($_POST['password']) < 6) $msg = 'Password minimal 6 karakter.';
        else try {
            $pdo->prepare('INSERT INTO users(nama,email,password,role) VALUES(?,?,?,"agen")')->execute([trim($_POST['nama']), trim($_POST['email']), password_hash($_POST['password'], PASSWORD_DEFAULT)]);
            $msg = 'Akun agen dibuat.';
        } catch (PDOException $x) {
            $msg = 'Email sudah terdaftar.';
        }
    }
    if ($a == 'hapus') {
        try {
            $pdo->prepare('DELETE FROM users WHERE id_user=? AND role="agen"')->execute([(int)$_POST['id']]);
            $msg = 'Akun agen dihapus.';
        } catch (PDOException $x) {
            $msg = 'Gagal: agen masih punya properti atau catatan tindak lanjut.';
        }
    }
}
$l = $pdo->query('SELECT u.*,(SELECT COUNT(*) FROM properti p WHERE p.id_agen=u.id_user) n FROM users u WHERE role IN("admin","agen") ORDER BY role,nama');
$title = 'Akun Agen';
require '../includes/header.php'; ?>
<h2>Kelola Akun Agen</h2><?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<form class="box f" method="post"><?= csrf() ?><input type="hidden" name="a" value="tambah">
    <div class="row"><input name="nama" placeholder="Nama agen" required><input type="email" name="email" placeholder="Email" required><input type="password" name="password" placeholder="Password awal" required><button>Tambah agen</button></div>
</form>
<table>
    <tr>
        <th>Nama</th>
        <th>Email</th>
        <th>Peran</th>
        <th>Properti</th>
        <th></th>
    </tr><?php foreach ($l as $x): ?><tr>
            <td><?= e($x['nama']) ?></td>
            <td><?= e($x['email']) ?></td>
            <td><?= e($x['role']) ?></td>
            <td><?= $x['n'] ?></td>
            <td>
                <?php if ($x['role'] == 'agen'): ?><form method="post" style="display:inline" onsubmit="return confirm('Hapus akun ini?')"><?= csrf() ?><input type="hidden" name="a" value="hapus"><input type="hidden" name="id" value="<?= $x['id_user'] ?>"><button class="del">Hapus</button></form><?php endif; ?></td>
        </tr><?php endforeach; ?>
</table>
<?php require '../includes/footer.php';
