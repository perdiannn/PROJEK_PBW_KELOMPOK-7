<?php require 'config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $s = $pdo->prepare('SELECT * FROM users WHERE email=?');
    $s->execute([$_POST['email']]);
    $x = $s->fetch();
    if ($x && password_verify($_POST['password'], $x['password'])) {
        session_regenerate_id(true);
        $_SESSION['u'] = ['id' => $x['id_user'], 'nama' => $x['nama'], 'role' => $x['role']];
        header('Location: ' . ($x['role'] == 'pembeli' ? BASE . '/' : BASE . '/admin/'));
        exit;
    }
    $err = 'Email atau password salah.';
}
$title = 'Masuk';
require 'includes/header.php'; ?>
<div class="box" style="max-width:380px;margin:30px auto">
    <h2 style="margin-top:0">Masuk</h2><?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
    <form class="f" method="post"><?= csrf() ?><input type="email" name="email" placeholder="Email" required><input type="password" name="password" placeholder="Password" required><button>Masuk</button></form>
    <p class="mut">Belum punya akun? <a href="register.php">Daftar</a></p>
</div>
<?php require 'includes/footer.php';
