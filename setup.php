<?php // Jalankan SEKALI lalu HAPUS file ini.
require 'config.php';
$pdo->prepare('INSERT IGNORE INTO users(nama,email,password,role) VALUES(?,?,?,?)')->execute(['Administrator', 'admin@sipro.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
echo 'Admin dibuat: admin@sipro.com / admin123. Hapus setup.php sekarang.';
