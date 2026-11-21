<?php
require_once 'config.php';
need(['pembeli']);
$u = user();

$s = $pdo->prepare(
    'SELECT r.*, p.judul, p.tipe, p.lokasi, p.harga, ag.nama AS nama_agen,
            (SELECT MIN(j.tanggal_survei) FROM jadwal_survei j WHERE j.id_permintaan = r.id_permintaan AND j.status_survei IN ("Dijadwalkan", "Dikonfirmasi")) AS survei,
            (SELECT COUNT(*) FROM pesan m WHERE m.id_permintaan = r.id_permintaan) AS total_pesan,
            (SELECT COUNT(*) FROM usulan x WHERE x.id_permintaan = r.id_permintaan AND x.status = "Menunggu" AND x.id_pengusul != ?) AS perlu_respons
    FROM permintaan r
    JOIN properti p ON p.id_properti = r.id_properti
    JOIN users ag ON ag.id_user = p.id_agen
    WHERE r.id_user = ?
    ORDER BY r.tanggal DESC'
);
$s->execute([$u['id'], $u['id']]);
$rows = $s->fetchAll();

$title = 'Pengajuan Saya';
require 'includes/header.php';
?>
<h2>Pengajuan & Minat Properti Saya</h2>
<p class="mut">Pantau status pengajuan, jadwal survei fisik, dan buka ruang percakapan dua arah dengan agen.</p>

<?php tampil_flash(); ?>

<div class="tablewrap">
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Properti</th>
                <th>Agen Pengelola</th>
                <th>Status</th>
                <th>Jadwal Survei</th>
                <th>Pesan / Notifikasi</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(tgl($r['tanggal'])) ?></td>
                    <td>
                        <b><?= e($r['judul']) ?></b>
                        <div class="mut"><?= e($r['tipe']) ?> · <?= e($r['lokasi']) ?> · <?= rp($r['harga']) ?></div>
                    </td>
                    <td><?= e($r['nama_agen']) ?></td>
                    <td>
                        <span class="tag <?= e(str_replace(' ', '', strtok($r['status'], ' '))) ?>"><?= e($r['status']) ?></span>
                        <?php if ($r['harga_sepakat']): ?>
                            <div class="mut" style="font-size:12px;margin-top:4px">Harga deal: <b><?= rp($r['harga_sepakat']) ?></b></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $r['survei'] ? '<span class="tag" style="background:#e0f2fe;color:#0369a1">' . e(tgl($r['survei'])) . '</span>' : '<span class="mut">-</span>' ?>
                    </td>
                    <td>
                        <span class="badge" style="background:#f3f4f6;color:#374151"><?= (int)$r['total_pesan'] ?> Pesan</span>
                        <?php if ($r['perlu_respons'] > 0): ?>
                            <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:4px">Butuh respon</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn" href="<?= BASE ?>/percakapan.php?id=<?= (int)$r['id_permintaan'] ?>">Buka Percakapan</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="7" class="mut" style="text-align:center;padding:24px">
                        Belum ada pengajuan properti. Silakan jelajahi <a href="<?= BASE ?>/">Katalog Properti</a> dan kirimkan minat Anda.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'includes/footer.php'; ?>
