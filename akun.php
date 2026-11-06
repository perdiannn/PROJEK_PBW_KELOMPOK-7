<?php require 'config.php';
need(['pembeli']);
$u = user();
$s = $pdo->prepare('SELECT r.*,p.judul,(SELECT MAX(tanggal_survei) FROM jadwal_survei j WHERE j.id_permintaan=r.id_permintaan) survei FROM permintaan r JOIN properti p USING(id_properti) WHERE r.id_user=? ORDER BY r.tanggal DESC');
$s->execute([$u['id']]);
$title = 'Pengajuan Saya';
require 'includes/header.php'; ?>
<h2>Pengajuan Saya</h2>
<table>
    <tr>
        <th>Tanggal</th>
        <th>Properti</th>
        <th>Status</th>
        <th>Jadwal survei</th>
    </tr>
    <?php foreach ($s as $r): ?><tr>
            <td><?= e($r['tanggal']) ?></td>
            <td><?= e($r['judul']) ?></td>
            <td><span class="tag"><?= e($r['status']) ?></span></td>
            <td><?= e($r['survei'] ?? '-') ?></td>
        </tr><?php endforeach; ?>
</table>
<?php require 'includes/footer.php';
