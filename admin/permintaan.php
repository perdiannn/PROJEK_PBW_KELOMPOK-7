<?php
require_once '../config.php';
need(['admin', 'agen']);
$u = user();

if ($u['role'] === 'agen') {
    go(BASE . '/agen/permintaan.php' . (isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : ''));
}

// Halaman Monitoring Pengajuan untuk Admin
$agentFilter = (int)($_GET['agen'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$where = ['1=1'];
$params = [];

if ($agentFilter > 0) {
    $where[] = 'p.id_agen = ?';
    $params[] = $agentFilter;
}
if ($statusFilter !== '') {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}

$agents = $pdo->query("SELECT id_user, nama FROM users WHERE role = 'agen' ORDER BY nama")->fetchAll();

$sql = 'SELECT r.*, b.nama AS nama_pembeli, b.email AS email_pembeli, b.telepon AS telepon_pembeli,
            p.judul, p.tipe, p.harga, ag.nama AS nama_agen,
            (SELECT COUNT(*) FROM pesan m WHERE m.id_permintaan = r.id_permintaan) AS total_pesan,
            (SELECT MIN(j.tanggal_survei) FROM jadwal_survei j WHERE j.id_permintaan = r.id_permintaan AND j.status_survei IN ("Dijadwalkan", "Dikonfirmasi")) AS survei
        FROM permintaan r
        JOIN properti p ON p.id_properti = r.id_properti
        JOIN users b ON b.id_user = r.id_user
        JOIN users ag ON ag.id_user = p.id_agen
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY r.tanggal DESC';

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$title = 'Monitoring Pengajuan Pembeli (Admin)';
require '../includes/header.php';
?>
<h2>Monitoring Pengajuan Pembeli & Aktivitas Agen</h2>
<p class="mut">Admin dapat memantau seluruh proses pengajuan, komunikasi agen dengan pembeli, dan perkembangan prospek.</p>

<form class="box f" method="get">
    <div class="row">
        <label>Filter Agen:
            <select name="agen">
                <option value="">Semua Agen</option>
                <?php foreach ($agents as $ag): ?>
                    <option value="<?= $ag['id_user'] ?>" <?= $agentFilter === (int)$ag['id_user'] ? 'selected' : '' ?>>
                        <?= e($ag['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Filter Status:
            <select name="status">
                <option value="">Semua Status</option>
                <?php foreach (['Diajukan', 'Dihubungi', 'Survei Dijadwalkan', 'Negosiasi Harga', 'Harga Disepakati', 'Selesai', 'Dibatalkan'] as $st): ?>
                    <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button>Terapkan Filter</button>
        <?php if ($agentFilter || $statusFilter): ?>
            <a class="btn sec" href="permintaan.php">Reset</a>
        <?php endif; ?>
    </div>
</form>

<div class="tablewrap">
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Calon Pembeli</th>
                <th>Properti</th>
                <th>Agen Penanggung Jawab</th>
                <th>Status</th>
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
                    <td><b><?= e($r['nama_agen']) ?></b></td>
                    <td>
                        <span class="tag <?= e(str_replace(' ', '', strtok($r['status'], ' '))) ?>"><?= e($r['status']) ?></span>
                        <?php if ($r['harga_sepakat']): ?>
                            <div class="mut" style="font-size:12px;margin-top:4px">Sepakat: <b><?= rp($r['harga_sepakat']) ?></b></div>
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
                    <td colspan="7" class="mut" style="text-align:center;padding:24px">
                        Tidak ada data pengajuan yang cocok dengan kriteria filter.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require '../includes/footer.php'; ?>
