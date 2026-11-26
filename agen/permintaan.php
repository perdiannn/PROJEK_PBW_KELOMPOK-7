<?php
require_once '../config.php';
need(['agen']);
$u = user();

$s = $pdo->prepare(
    'SELECT r.*, b.nama AS nama_pembeli, b.email AS email_pembeli, b.telepon AS telepon_pembeli,
            p.judul, p.tipe, p.harga,
            (SELECT COUNT(*) FROM pesan m WHERE m.id_permintaan = r.id_permintaan) AS total_pesan,
            (SELECT COUNT(*) FROM usulan x WHERE x.id_permintaan = r.id_permintaan AND x.status = "Menunggu" AND x.id_pengusul != ?) AS butuh_respon,
            (SELECT MIN(j.tanggal_survei) FROM jadwal_survei j WHERE j.id_permintaan = r.id_permintaan AND j.status_survei IN ("Dijadwalkan", "Dikonfirmasi")) AS survei
    FROM permintaan r
    JOIN properti p ON p.id_properti = r.id_properti
    JOIN users b ON b.id_user = r.id_user
    WHERE p.id_agen = ?
    ORDER BY butuh_respon DESC, r.tanggal DESC'
);
$s->execute([$u['id'], $u['id']]);
$rows = $s->fetchAll();

$title = 'Pengajuan Pembeli (Agen)';
require '../includes/header.php';
?>
<h2>Daftar Pengajuan Calon Pembeli</h2>
<p class="mut">Kelola komunikasi calon pembeli, konfirmasi jadwal survei, tanggapi negosiasi harga, dan catat penjualan properti.</p>

<?php tampil_flash(); ?>

<div class="tablewrap">
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Calon Pembeli</th>
                <th>Properti</th>
                <th>Status Pengajuan</th>
                <th>Jadwal Survei</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(tgl($r['tanggal'])) ?></td>
                    <td>
                        <b><?= e($r['nama_pembeli']) ?></b>
                        <div class="mut"><?= e($r['email_pembeli']) ?><?= $r['telepon_pembeli'] ? ' · ' . e($r['telepon_pembeli']) : '' ?></div>
                    </td>
                    <td>
                        <b><?= e($r['judul']) ?></b>
                        <div class="mut"><?= e($r['tipe']) ?> · <?= rp($r['harga']) ?></div>
                    </td>
                    <td>
                        <span class="tag <?= e(str_replace(' ', '', strtok($r['status'], ' '))) ?>"><?= e($r['status']) ?></span>
                        <?php if ($r['harga_sepakat']): ?>
                            <div class="mut" style="font-size:12px;margin-top:4px">Harga deal: <b><?= rp($r['harga_sepakat']) ?></b></div>
                        <?php endif; ?>
                        <?php if ($r['butuh_respon'] > 0): ?>
                            <div style="margin-top:4px"><span class="badge" style="background:#fef3c7;color:#92400e"> Perlu Respon</span></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $r['survei'] ? '<span class="tag" style="background:#e0f2fe;color:#0369a1">' . e(tgl($r['survei'])) . '</span>' : '<span class="mut">-</span>' ?>
                    </td>
                    <td>
                        <a class="btn" href="<?= BASE ?>/percakapan.php?id=<?= (int)$r['id_permintaan'] ?>">Buka Percakapan</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6" class="mut" style="text-align:center;padding:24px">
                        Belum ada pengajuan atau calon pembeli untuk properti yang Anda kelola saat ini.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require '../includes/footer.php'; ?>
