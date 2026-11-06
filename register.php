<?php require 'config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (strlen($_POST['password']) < 6) $err = 'Password minimal 6 karakter.';
    else try {
        $pdo->prepare('INSERT INTO users(nama,email,password,role) VALUES(?,?,?,"pembeli")')->execute([trim($_POST['nama']), trim($_POST['email']), password_hash($_POST['password'], PASSWORD_DEFAULT)]);
        header('Location: login.php');
        exit;
    } catch (PDOException $x) {
        $err = 'Email sudah terdaftar.';
    }
}
$title = 'Daftar';
require 'includes/header.php'; ?>
<div class="box" style="max-width:380px;margin:30px auto">
    <h2 style="margin-top:0">Daftar Pembeli</h2><?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
    <form class="f" method="post"><?= csrf() ?><input name="nama" placeholder="Nama lengkap" required><input type="email" name="email" placeholder="Email" required><input type="password" name="password" placeholder="Password" required><button>Daftar</button></form>
</div>
<?php require 'includes/footer.php';
