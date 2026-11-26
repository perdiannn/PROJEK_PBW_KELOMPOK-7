<?php require 'config.php';
need(['admin', 'agen', 'pembeli']);
$u = user();
$id = (int)($_GET['id'] ?? 0);
$q = $pdo->prepare('SELECT r.*,b.nama pembeli,b.id_user pembeli_id,p.judul,p.id_agen,p.harga,p.status status_properti,a.nama agen FROM permintaan r JOIN users b ON b.id_user=r.id_user JOIN properti p ON p.id_properti=r.id_properti JOIN users a ON a.id_user=p.id_agen WHERE r.id_permintaan=?');
$q->execute([$id]);
$r = $q->fetch();
if (!$r) exit('Pengajuan tidak ditemukan.');
$isBuyer = $u['role'] === 'pembeli';
$allowed = ($u['role'] === 'admin') || ($isBuyer ? (int)$r['pembeli_id'] === (int)$u['id'] : (int)$r['id_agen'] === (int)$u['id']);
if (!$allowed) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke percakapan ini.');
}
$err = '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? '';
    try {
        if ($a === 'pesan') {
            $body = trim($_POST['isi'] ?? '');
            if ($body === '') throw new Exception('Pesan tidak boleh kosong.');
            $pdo->prepare('INSERT INTO pesan(id_permintaan,id_pengirim,isi) VALUES(?,?,?)')->execute([$id, $u['id'], $body]);
        } elseif ($a === 'usul') {
            $kind = $_POST['jenis'] ?? '';
            if (!in_array($kind, ['Jadwal', 'Harga'], true)) throw new Exception('Jenis usulan tidak valid.');
            $pending = $pdo->prepare("SELECT COUNT(*) FROM usulan WHERE id_permintaan=? AND jenis=? AND status='Menunggu'");
            $pending->execute([$id, $kind]);
            if ($pending->fetchColumn() > 0) throw new Exception('Masih ada usulan ' . $kind . ' yang menunggu respons. Tanggapi usulan tersebut terlebih dahulu.');
            $date = null;
            $price = null;
            if ($kind === 'Jadwal') {
                $date = str_replace('T', ' ', $_POST['tanggal_usulan'] ?? '');
                if (!$date || strtotime($date) <= time()) throw new Exception('Jadwal harus berada di masa mendatang.');
            } else {
                $price = (int)($_POST['harga_usulan'] ?? 0);
                if ($price <= 0) throw new Exception('Harga harus lebih dari nol.');
            }
            $note = trim($_POST['catatan'] ?? '');
            $pdo->prepare('INSERT INTO usulan(id_permintaan,jenis,id_pengusul,tanggal_usulan,harga_usulan,catatan) VALUES(?,?,?,?,?,?)')->execute([$id, $kind, $u['id'], $date, $price, $note]);
            $status = $kind === 'Jadwal' ? 'Menunggu Persetujuan Jadwal' : 'Menunggu Persetujuan Harga';
            $pdo->prepare('UPDATE permintaan SET status=? WHERE id_permintaan=?')->execute([$status, $id]);
        } elseif ($a === 'respon') {
            $uid = (int)($_POST['id_usulan'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            if (!in_array($decision, ['Disetujui', 'Ditolak'], true)) throw new Exception('Respons tidak valid.');
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT * FROM usulan WHERE id_usulan=? AND id_permintaan=? AND status='Menunggu' FOR UPDATE");
            $st->execute([$uid, $id]);
            $proposal = $st->fetch();
            if (!$proposal) throw new Exception('Usulan sudah ditanggapi atau tidak ditemukan.');
            if ((int)$proposal['id_pengusul'] === (int)$u['id']) throw new Exception('Pengusul tidak dapat menyetujui usulannya sendiri.');
            $pdo->prepare('UPDATE usulan SET status=?,responded_by=?,responded_at=NOW() WHERE id_usulan=?')->execute([$decision, $u['id'], $uid]);
            if ($decision === 'Disetujui') {
                if ($proposal['jenis'] === 'Jadwal') {
                    $pdo->prepare("INSERT INTO jadwal_survei(id_permintaan,id_usulan,tanggal_survei,status_survei) VALUES(?,?,?,'Dijadwalkan')")->execute([$id, $uid, $proposal['tanggal_usulan']]);
                    $pdo->prepare("UPDATE permintaan SET status='Survei Dijadwalkan' WHERE id_permintaan=?")->execute([$id]);
                } else {
                    $pdo->prepare("UPDATE permintaan SET harga_sepakat=?,status='Harga Disepakati' WHERE id_permintaan=?")->execute([$proposal['harga_usulan'], $id]);
                }
            } else {
                $pdo->prepare("UPDATE permintaan SET status='Dihubungi' WHERE id_permintaan=? AND status IN ('Menunggu Persetujuan Jadwal','Menunggu Persetujuan Harga')")->execute([$id]);
            }
            $pdo->commit();
        } elseif ($a === 'transaksi') {
            if ($u['role'] !== 'agen') throw new Exception('Hanya agen yang dapat mencatat transaksi.');
            $check = $pdo->prepare("SELECT harga_sepakat FROM permintaan WHERE id_permintaan=? AND status='Harga Disepakati'");
            $check->execute([$id]);
            $final = $check->fetchColumn();
            if (!$final) throw new Exception('Transaksi hanya dapat dicatat setelah harga disepakati kedua pihak.');
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO transaksi_penjualan(id_properti,id_pembeli,id_permintaan,tanggal_transaksi,harga_final,status) VALUES(?,?,?,CURDATE(),?,'Selesai')")->execute([$r['id_properti'], $r['pembeli_id'], $id, $final]);
            $pdo->prepare("UPDATE properti SET status='Terjual' WHERE id_properti=?")->execute([$r['id_properti']]);
            $pdo->prepare("UPDATE permintaan SET status='Selesai' WHERE id_permintaan=?")->execute([$id]);
            $pdo->commit();
        }
        go('percakapan.php?id=' . $id . '#detail-komunikasi');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e instanceof PDOException ? 'Terjadi kesalahan penyimpanan. Periksa apakah transaksi sudah tercatat.' : $e->getMessage();
    }
}
$msgs = $pdo->prepare('SELECT m.*,u.nama,u.role FROM pesan m JOIN users u ON u.id_user=m.id_pengirim WHERE m.id_permintaan=? ORDER BY m.created_at');
$msgs->execute([$id]);
$messages = $msgs->fetchAll();
$props = $pdo->prepare('SELECT x.*,u.nama pengusul,u.role role_pengusul,v.nama penanggap FROM usulan x JOIN users u ON u.id_user=x.id_pengusul LEFT JOIN users v ON v.id_user=x.responded_by WHERE x.id_permintaan=? ORDER BY x.created_at DESC');
$props->execute([$id]);
$proposals = $props->fetchAll();
$sched = $pdo->prepare('SELECT * FROM jadwal_survei WHERE id_permintaan=? ORDER BY tanggal_survei DESC');
$sched->execute([$id]);
$schedules = $sched->fetchAll();
$title = 'Percakapan Pengajuan';
require 'includes/header.php'; ?>
<p><a href="<?= $isBuyer ? BASE . '/akun.php' : ($u['role'] === 'admin' ? BASE . '/admin/permintaan.php' : BASE . '/agen/permintaan.php') ?>">&larr; Kembali ke daftar pengajuan</a></p>
<h2><?= e($r['judul']) ?></h2>
<p class="mut">Pembeli: <?= e($r['pembeli']) ?> · Agen: <?= e($r['agen']) ?> · Harga awal: <?= rp($r['harga']) ?></p><?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif ?><div class="box" id="detail-komunikasi">
    <h3>Detail Komunikasi</h3>
    <div class="chatlist">
        <?php foreach ($messages as $m): ?>
            <div class="chat <?= (int)$m['id_pengirim'] === (int)$u['id'] ? 'mine' : '' ?>">
                <div class="chat-header">
                    <b><?= e($m['nama']) ?></b>
                    <small class="chat-role"><?= (int)$m['id_pengirim'] === (int)$u['id'] ? '(Anda)' : '(' . e(ucfirst($m['role'])) . ')' ?></small>
                </div>
                <div class="chat-body"><?= nl2br(e($m['isi'])) ?></div>
                <div class="chat-time">
                    <small><?= e(tgl($m['created_at'])) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?>
            <p class="mut" style="text-align:center; padding:20px;">Belum ada pesan. Agen atau pembeli dapat memulai percakapan di sini.</p>
        <?php endif; ?>
    </div>
    <?php if ($u['role'] !== 'admin'): ?>
        <form method="post" class="chat-input-bar">
            <?= csrf() ?>
            <input type="hidden" name="a" value="pesan">
            <textarea name="isi" rows="1" placeholder="Ketik pesan..." required></textarea>
            <button class="btn btn-send">Kirim</button>
        </form>
    <?php else: ?>
        <p class="mut" style="margin-top:10px; font-size:13px;"><i>ℹAkun Administrator memantau percakapan dalam mode baca (read-only).</i></p>
    <?php endif; ?>
</div>
<div class="box">
    <h3>Usulan Jadwal & Kesepakatan Harga</h3>
    <p class="mut" style="margin-top:0;">Pihak yang mengajukan usulan tidak dapat menyetujui usulannya sendiri. Persetujuan hanya dapat diberikan oleh pihak lawan.</p>

    <?php
    $activeProposal = null;
    foreach ($proposals as $item) {
        if ($item['status'] === 'Menunggu') {
            $activeProposal = $item;
            break;
        }
    }
    ?>

    <?php if ($activeProposal): ?>
        <div class="pending-box">
            <h4>Usulan Sedang Menunggu Konfirmasi</h4>
            <div class="pending-body">
                <p><strong>Jenis Usulan:</strong> <?= $activeProposal['jenis'] === 'Jadwal' ? 'Jadwal Survei Lapangan' : 'Negosiasi Harga Properti' ?></p>
                <p><strong>Nilai yang Diajukan:</strong> <b style="font-size:16px; color:#0f2a43"><?= $activeProposal['jenis'] === 'Jadwal' ? e(tgl($activeProposal['tanggal_usulan'])) : rp($activeProposal['harga_usulan']) ?></b></p>
                <p><strong>Diajukan oleh:</strong> <?= e($activeProposal['pengusul']) ?> (<?= e(ucfirst($activeProposal['role_pengusul'])) ?>) · <?= e(tgl($activeProposal['created_at'])) ?></p>
                <div class="pending-note">
                    <b>Catatan Pengusul:</b>
                    <div style="margin-top:4px"><?= $activeProposal['catatan'] ? e($activeProposal['catatan']) : '<em class="mut">Tidak ada catatan khusus yang dilampirkan.</em>' ?></div>
                </div>
            </div>
            <?php if ($u['role'] !== 'admin' && (int)$activeProposal['id_pengusul'] !== (int)$u['id']): ?>
                <div class="pending-actions">
                    <form method="post" style="display:inline"><?= csrf() ?>
                        <input type="hidden" name="a" value="respon">
                        <input type="hidden" name="id_usulan" value="<?= $activeProposal['id_usulan'] ?>">
                        <input type="hidden" name="decision" value="Disetujui">
                        <button class="btn btn-approve">Setujui Usulan</button>
                    </form>
                    <form method="post" style="display:inline" onsubmit="return confirm('Apakah Anda yakin ingin menolak usulan ini?')"><?= csrf() ?>
                        <input type="hidden" name="a" value="respon">
                        <input type="hidden" name="id_usulan" value="<?= $activeProposal['id_usulan'] ?>">
                        <input type="hidden" name="decision" value="Ditolak">
                        <button class="btn btn-reject">Tolak Usulan</button>
                    </form>
                </div>
            <?php elseif ((int)$activeProposal['id_pengusul'] === (int)$u['id']): ?>
                <small class="mut">ℹAnda yang mengajukan usulan ini. Menunggu tanggapan dari pihak lawan.</small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h4 style="margin:20px 0 8px 0;">Riwayat Pengajuan Usulan</h4>
    <?php if ($proposals): ?>
        <div class="tablewrap">
            <table>
                <thead>
                    <tr>
                        <th>Waktu Usul</th>
                        <th>Jenis</th>
                        <th>Detail Usulan</th>
                        <th>Pengusul</th>
                        <th>Catatan Pengusul</th>
                        <th>Status</th>
                        <th>Keterangan Tanggapan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proposals as $x): ?>
                        <tr>
                            <td><?= e(tgl($x['created_at'])) ?></td>
                            <td><b><?= $x['jenis'] === 'Jadwal' ? 'Jadwal' : 'Harga' ?></b></td>
                            <td><b><?= $x['jenis'] === 'Jadwal' ? e(tgl($x['tanggal_usulan'])) : rp($x['harga_usulan']) ?></b></td>
                            <td><?= e($x['pengusul']) ?> <small class="mut">(<?= e($x['role_pengusul']) ?>)</small></td>
                            <td style="max-width:220px">
                                <?php if ($x['catatan']): ?>
                                    <span class="note-pill" title="<?= e($x['catatan']) ?>"> <?= e($x['catatan']) ?></span>
                                <?php else: ?>
                                    <span class="mut">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="tag <?= e($x['status']) ?>"><?= e($x['status']) ?></span></td>
                            <td>
                                <?php if ($x['status'] === 'Menunggu'): ?>
                                    <span class="mut">Menunggu tanggapan</span>
                                <?php elseif ($x['responded_by']): ?>
                                    <small><?= e($x['penanggap']) ?><br><span class="mut"><?= e(tgl($x['responded_at'])) ?></span></small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="mut">Belum ada riwayat usulan jadwal maupun harga.</p>
    <?php endif; ?>

    <?php if ($u['role'] !== 'admin' && $r['status'] !== 'Selesai' && $r['status_properti'] !== 'Terjual'): ?>
        <div style="margin-top:24px; padding-top:16px; border-top:1px dashed #cbd5e1">
            <h4 style="margin:0 0 12px 0;">Ajukan Usulan Baru</h4>
            <?php if ($activeProposal): ?>
                <div class="alert" style="margin:0">
                    Saat ini masih ada usulan yang menunggu tanggapan. Selesaikan atau tanggapi usulan aktif di atas sebelum membuat usulan baru.
                </div>
            <?php else: ?>
                <form method="post" class="f mutedbox">
                    <?= csrf() ?>
                    <input type="hidden" name="a" value="usul">
                    <div class="row">
                        <label>Jenis Usulan:
                            <select name="jenis" id="jenis" onchange="toggleProposal()">
                                <option value="Jadwal">Usulan Jadwal Survei</option>
                                <option value="Harga">Usulan Negosiasi Harga</option>
                            </select>
                        </label>
                        <label id="datefield">Tanggal dan Waktu Survei:
                            <input type="datetime-local" name="tanggal_usulan" min="<?= date('Y-m-d\TH:i') ?>">
                        </label>
                        <label id="pricefield" hidden>Penawaran Harga (Rp):
                            <input type="number" name="harga_usulan" min="1" placeholder="Contoh: 450000000" class="format-rupiah">
                        </label>
                    </div>
                    <label>Catatan Pengajuan (Opsional):
                        <input name="catatan" maxlength="255">
                    </label>
                    <button>Kirim Usulan</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<div class="box">
    <h3>Jadwal survei yang disepakati</h3><?php if (!$schedules): ?><p class="mut">Belum ada jadwal yang disetujui kedua pihak.</p><?php else: ?><ul><?php foreach ($schedules as $sc): ?><li><?= e($sc['tanggal_survei']) ?> — <?= e($sc['status_survei']) ?></li><?php endforeach ?></ul><?php endif ?>
</div><?php if ($u['role'] === 'agen' && $r['status'] === 'Harga Disepakati'): ?><div class="box">
        <h3>Catat transaksi penjualan</h3>
        <p>Harga final yang disepakati: <b><?= rp($r['harga_sepakat']) ?></b></p>
        <form method="post" onsubmit="return confirm('Catat transaksi dan ubah status properti menjadi Terjual?')"><?= csrf() ?><input type="hidden" name="a" value="transaksi"><button>Catat transaksi selesai</button></form>
    </div><?php endif ?><script>
    function toggleProposal() {
        let v = document.getElementById('jenis').value;
        document.getElementById('datefield').hidden = v !== 'Jadwal';
        document.getElementById('pricefield').hidden = v !== 'Harga'
    }
</script><?php require 'includes/footer.php';
