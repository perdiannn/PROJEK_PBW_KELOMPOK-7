<?php
// Logika bersama agen <-> pembeli untuk satu pengajuan (permintaan):
//   - usulan jadwal survei & harga yang harus disetujui pihak lawan (saling approve)
//   - percakapan dua arah
// Dipakai oleh admin/permintaan.php (agen) dan pengajuan.php (pembeli).
// Admin tidak ikut di sini: admin hanya mengelola data properti & akun agen.

const STATUS_TUTUP = ['Selesai', 'Dibatalkan'];

// DATA

// Ambil pengajuan beserta pengecekan akses: pembeli hanya miliknya, agen hanya properti yang ia pegang.
function neg_ambil(PDO $pdo, int $id, array $u)
{
    $s = $pdo->prepare(
        'SELECT r.id_permintaan, r.id_user, r.id_properti, r.tanggal, r.pesan, r.status, r.harga_sepakat,
                p.judul, p.harga, p.tipe, p.status AS status_properti, p.id_agen,
                pb.nama AS nama_pembeli, pb.email AS email_pembeli,
                ag.nama AS nama_agen, ag.telepon AS telepon_agen
        FROM permintaan r
        JOIN properti p ON p.id_properti = r.id_properti
        JOIN users pb ON pb.id_user = r.id_user
        JOIN users ag ON ag.id_user = p.id_agen
        WHERE r.id_permintaan = ?'
    );
    $s->execute([$id]);
    $r = $s->fetch();
    if (!$r) return null;
    if ($u['role'] === 'pembeli' && (int)$r['id_user'] !== (int)$u['id']) return null;
    if ($u['role'] === 'agen' && (int)$r['id_agen'] !== (int)$u['id']) return null;
    if (!in_array($u['role'], ['pembeli', 'agen'], true)) return null;
    return $r;
}

// Properti kembali "Tersedia" bila tidak ada lagi pengajuan yang sudah sepakat harga.
function neg_lepas_properti(PDO $pdo, int $idProperti): void
{
    $pdo->prepare(
        'UPDATE properti SET status = "Tersedia"
        WHERE id_properti = ? AND status = "Dalam Negosiasi"
        AND NOT EXISTS (SELECT 1 FROM permintaan WHERE id_properti = ? AND status = "Harga Disepakati")'
    )->execute([$idProperti, $idProperti]);
}


// VALIDASI INPUT

// Mengembalikan [tanggal|null, harga|null] dari $_POST sesuai jenis usulan.
function neg_nilai(string $jenis): array
{
    if ($jenis === 'Jadwal') {
        $raw = (string)($_POST['tgl'] ?? '');
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $raw) ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $raw);
        if (!$d) throw new DomainException('Format tanggal tidak valid.');
        if ($d <= new DateTime()) throw new DomainException('Tanggal survei harus di masa depan.');
        return [$d->format('Y-m-d H:i:00'), null];
    }
    if ($jenis === 'Harga') {
        $h = preg_replace('/\D/', '', (string)($_POST['harga'] ?? ''));
        if ($h === '' || strlen($h) > 13 || (int)$h <= 0) throw new DomainException('Nominal harga tidak valid.');
        return [null, (int)$h];
    }
    throw new DomainException('Jenis usulan tidak dikenal.');
}

// Syarat umum sebelum boleh membuat usulan baru.
function neg_boleh_usul(PDO $pdo, int $idPermintaan, int $idProperti, string $status, string $jenis): void
{
    if (in_array($status, STATUS_TUTUP, true)) throw new DomainException('Pengajuan ini sudah ditutup.');
    if (!in_array($jenis, ['Jadwal', 'Harga'], true)) throw new DomainException('Jenis usulan tidak dikenal.');
    $sp = $pdo->prepare('SELECT status FROM properti WHERE id_properti = ?');
    $sp->execute([$idProperti]);
    if ($sp->fetchColumn() === 'Terjual') throw new DomainException('Properti sudah terjual.');
    if ($jenis === 'Harga' && $status === 'Harga Disepakati') throw new DomainException('Harga sudah disepakati.');
}

function neg_buat(PDO $pdo, int $idPermintaan, int $idPengusul, string $jenis, $induk = null): void
{
    [$tgl, $harga] = neg_nilai($jenis);
    $cat = mb_substr(trim((string)($_POST['catatan'] ?? '')), 0, 255);
    $pdo->prepare(
        'INSERT INTO usulan (id_permintaan, jenis, id_pengusul, tanggal_usulan, harga_usulan, catatan, id_induk)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$idPermintaan, $jenis, $idPengusul, $tgl, $harga, $cat === '' ? null : $cat, $induk]);
}

// Ambil usulan yang masih Menunggu (dikunci) milik pengajuan ini.
function neg_usulan_menunggu(PDO $pdo, int $idPermintaan, int $idUsulan): array
{
    $s = $pdo->prepare('SELECT * FROM usulan WHERE id_usulan = ? AND id_permintaan = ? FOR UPDATE');
    $s->execute([$idUsulan, $idPermintaan]);
    $x = $s->fetch();
    if (!$x) throw new DomainException('Usulan tidak ditemukan.');
    if ($x['status'] !== 'Menunggu') throw new DomainException('Usulan ini sudah ditanggapi.');
    return $x;
}


// PROSES POST (agen & pembeli) 


function neg_proses(PDO $pdo, array $r, array $u, string $back): void
{
    $id = (int)$r['id_permintaan'];
    $me = (int)$u['id'];
    $agen = ($u['role'] === 'agen');
    $a = $_POST['a'] ?? '';
    $ok = '';

    try {
        $pdo->beginTransaction();

        // Kunci baris pengajuan: dua klik / dua pihak bersamaan diproses berurutan.
        $st = $pdo->prepare('SELECT status, id_properti, id_user, harga_sepakat FROM permintaan WHERE id_permintaan = ? FOR UPDATE');
        $st->execute([$id]);
        $cur = $st->fetch();
        $status = $cur['status'];
        $idProp = (int)$cur['id_properti'];
        $tutup = in_array($status, STATUS_TUTUP, true);

        if ($a === 'pesan') {
            $isi = trim((string)($_POST['isi'] ?? ''));
            if ($isi === '') throw new DomainException('Pesan tidak boleh kosong.');
            if ($tutup) throw new DomainException('Pengajuan ini sudah ditutup.');
            $pdo->prepare('INSERT INTO pesan (id_permintaan, id_pengirim, isi) VALUES (?, ?, ?)')
                ->execute([$id, $me, mb_substr($isi, 0, 2000)]);
            $ok = 'Pesan terkirim.';
        } elseif ($a === 'usul') {
            $jenis = (string)($_POST['jenis'] ?? '');
            neg_boleh_usul($pdo, $id, $idProp, $status, $jenis);
            $c = $pdo->prepare('SELECT COUNT(*) FROM usulan WHERE id_permintaan = ? AND jenis = ? AND status = "Menunggu"');
            $c->execute([$id, $jenis]);
            if ($c->fetchColumn() > 0) {
                throw new DomainException('Masih ada usulan ' . strtolower($jenis) . ' yang menunggu tanggapan. Tanggapi atau batalkan dulu.');
            }
            neg_buat($pdo, $id, $me, $jenis);
            if ($jenis === 'Harga' && in_array($status, ['Diajukan', 'Dihubungi'], true)) {
                $pdo->prepare('UPDATE permintaan SET status = "Negosiasi Harga" WHERE id_permintaan = ?')->execute([$id]);
            }
            $ok = 'Usulan ' . strtolower($jenis) . ' dikirim. Menunggu tanggapan pihak lawan.';
        } elseif ($a === 'balas' || $a === 'setuju' || $a === 'tolak') {
            $x = neg_usulan_menunggu($pdo, $id, (int)($_POST['id_usulan'] ?? 0));
            if ((int)$x['id_pengusul'] === $me) {
                throw new DomainException('Anda tidak bisa menanggapi usulan Anda sendiri.');
            }
            if ($tutup) throw new DomainException('Pengajuan ini sudah ditutup.');
            $tandai = 'UPDATE usulan SET status = ?, responded_at = NOW() WHERE id_usulan = ? AND status = "Menunggu"';

            if ($a === 'tolak') {
                $pdo->prepare($tandai)->execute(['Ditolak', $x['id_usulan']]);
                $ok = 'Usulan ditolak.';
            } elseif ($a === 'balas') {
                // Penawaran balik: usulan lama ditolak, usulan baru dibuat atas nama penanggap.
                neg_boleh_usul($pdo, $id, $idProp, $status, $x['jenis']);
                $pdo->prepare($tandai)->execute(['Ditolak', $x['id_usulan']]);
                neg_buat($pdo, $id, $me, $x['jenis'], $x['id_usulan']);
                $ok = 'Usulan tandingan dikirim.';
            } else { // setuju
                if ($x['jenis'] === 'Jadwal') {
                    if (strtotime($x['tanggal_usulan']) <= time()) {
                        throw new DomainException('Tanggal usulan sudah lewat. Minta/ajukan tanggal baru.');
                    }
                    $pdo->prepare($tandai)->execute(['Disetujui', $x['id_usulan']]);
                    $pdo->prepare('INSERT INTO jadwal_survei (id_permintaan, tanggal_survei, status_survei) VALUES (?, ?, "Dikonfirmasi")')
                        ->execute([$id, $x['tanggal_usulan']]);
                    if ($status !== 'Harga Disepakati') {
                        $pdo->prepare('UPDATE permintaan SET status = "Survei Dijadwalkan" WHERE id_permintaan = ?')->execute([$id]);
                    }
                    $ok = 'Jadwal survei disetujui dan terkonfirmasi.';
                } else {
                    $sp = $pdo->prepare('SELECT status FROM properti WHERE id_properti = ? FOR UPDATE');
                    $sp->execute([$idProp]);
                    if ($sp->fetchColumn() === 'Terjual') throw new DomainException('Properti sudah terjual.');
                    $pdo->prepare($tandai)->execute(['Disetujui', $x['id_usulan']]);
                    $pdo->prepare('UPDATE permintaan SET status = "Harga Disepakati", harga_sepakat = ? WHERE id_permintaan = ?')
                        ->execute([$x['harga_usulan'], $id]);
                    $pdo->prepare('UPDATE properti SET status = "Dalam Negosiasi" WHERE id_properti = ? AND status = "Tersedia"')
                        ->execute([$idProp]);
                    $ok = 'Harga disetujui kedua pihak.';
                }
            }
        } elseif ($a === 'batal') {
            $x = neg_usulan_menunggu($pdo, $id, (int)($_POST['id_usulan'] ?? 0));
            if ((int)$x['id_pengusul'] !== $me) throw new DomainException('Hanya pengusul yang bisa membatalkan usulan.');
            $pdo->prepare('UPDATE usulan SET status = "Dibatalkan", responded_at = NOW() WHERE id_usulan = ?')->execute([$x['id_usulan']]);
            $ok = 'Usulan dibatalkan.';
        } elseif ($a === 'survei_status') {
            $ss = (string)($_POST['ss'] ?? '');
            if (!in_array($ss, ['Selesai', 'Dibatalkan'], true)) throw new DomainException('Status tidak valid.');
            if ($ss === 'Selesai' && !$agen) throw new DomainException('Hanya agen yang menandai survei selesai.');
            if ($tutup) throw new DomainException('Pengajuan ini sudah ditutup.');
            $q = $pdo->prepare('UPDATE jadwal_survei SET status_survei = ? WHERE id_survei = ? AND id_permintaan = ? AND status_survei = "Dikonfirmasi"');
            $q->execute([$ss, (int)($_POST['sid'] ?? 0), $id]);
            if (!$q->rowCount()) throw new DomainException('Jadwal tidak ditemukan atau sudah diproses.');
            $ok = 'Status survei diperbarui.';
        } elseif ($a === 'status') {
            $to = (string)($_POST['status'] ?? '');
            if ($tutup) throw new DomainException('Pengajuan ini sudah ditutup.');
            if ($to === 'Dihubungi') {
                if (!$agen || $status !== 'Diajukan') throw new DomainException('Perubahan status tidak diizinkan.');
                $pdo->prepare('UPDATE permintaan SET status = "Dihubungi" WHERE id_permintaan = ?')->execute([$id]);
                $ok = 'Ditandai sudah dihubungi.';
            } elseif ($to === 'Dibatalkan') {
                $pdo->prepare('UPDATE usulan SET status = "Dibatalkan", responded_at = NOW() WHERE id_permintaan = ? AND status = "Menunggu"')->execute([$id]);
                $pdo->prepare('UPDATE permintaan SET status = "Dibatalkan" WHERE id_permintaan = ?')->execute([$id]);
                neg_lepas_properti($pdo, $idProp);
                $ok = 'Pengajuan dibatalkan.';
            } else {
                throw new DomainException('Perubahan status tidak diizinkan.');
            }
        } elseif ($a === 'catatan' && $agen) {
            $cat = trim((string)($_POST['catatan'] ?? ''));
            if ($cat === '') throw new DomainException('Catatan tidak boleh kosong.');
            $pdo->prepare('INSERT INTO tindak_lanjut (id_permintaan, id_agen, catatan) VALUES (?, ?, ?)')->execute([$id, $me, $cat]);
            $ok = 'Catatan internal disimpan.';
        } elseif ($a === 'transaksi' && $agen) {
            if ($status !== 'Harga Disepakati' || $cur['harga_sepakat'] === null) {
                throw new DomainException('Transaksi hanya bisa dicatat setelah harga disetujui kedua pihak.');
            }
            $sp = $pdo->prepare('SELECT status FROM properti WHERE id_properti = ? FOR UPDATE');
            $sp->execute([$idProp]);
            if ($sp->fetchColumn() === 'Terjual') throw new DomainException('Properti sudah terjual.');
            $pdo->prepare('INSERT INTO transaksi_penjualan (id_properti, id_pembeli, tanggal_transaksi, harga_final) VALUES (?, ?, CURDATE(), ?)')
                ->execute([$idProp, $cur['id_user'], $cur['harga_sepakat']]);
            $pdo->prepare('UPDATE properti SET status = "Terjual" WHERE id_properti = ?')->execute([$idProp]);
            // Tutup semua usulan terbuka & pengajuan lain untuk properti ini
            $pdo->prepare(
                'UPDATE usulan x JOIN permintaan q ON q.id_permintaan = x.id_permintaan
                SET x.status = "Dibatalkan", x.responded_at = NOW()
                WHERE q.id_properti = ? AND x.status = "Menunggu"'
            )->execute([$idProp]);
            $pdo->prepare('UPDATE permintaan SET status = "Selesai" WHERE id_permintaan = ?')->execute([$id]);
            $pdo->prepare('UPDATE permintaan SET status = "Dibatalkan" WHERE id_properti = ? AND id_permintaan <> ? AND status NOT IN ("Selesai", "Dibatalkan")')
                ->execute([$idProp, $id]);
            $ok = 'Transaksi dicatat. Properti ditandai Terjual.';
        } else {
            throw new DomainException('Aksi tidak dikenal.');
        }

        $pdo->commit();
        flash($ok);
    } catch (DomainException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($ex->getMessage(), true);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
    header('Location: ' . $back);
    exit;
}


// TAMPILAN (dipanggil di dalam halaman, setelah header)


function neg_label_nilai(array $x): string
{
    return $x['jenis'] === 'Jadwal' ? tgl($x['tanggal_usulan']) : rp($x['harga_usulan']);
}

// Input nilai usulan (tanggal atau nominal)
function neg_input(string $jenis, string $nilai = ''): string
{
    if ($jenis === 'Jadwal') {
        return '<input type="datetime-local" name="tgl" min="' . date('Y-m-d\TH:i') . '" required>';
    }
    return '<input type="number" name="harga" min="1" step="1" placeholder="Nominal harga (Rp)" value="' . e($nilai) . '" required>';
}

function neg_form_aksi(string $a, int $idUsulan, string $label, string $kelas = '', string $konfirm = ''): string
{
    return '<form method="post" class="inline"' . ($konfirm ? ' onsubmit="return confirm(\'' . e($konfirm) . '\')"' : '') . '>' . csrf()
        . '<input type="hidden" name="a" value="' . e($a) . '">'
        . '<input type="hidden" name="id_usulan" value="' . $idUsulan . '">'
        . '<button class="' . e($kelas) . '">' . e($label) . '</button></form>';
}

function neg_panel(PDO $pdo, array $r, array $u, string $jenis): void
{
    $me = (int)$u['id'];
    $tutup = in_array($r['status'], STATUS_TUTUP, true);
    $terjual = ($r['status_properti'] === 'Terjual');
    $lawan = $u['role'] === 'agen' ? $r['nama_pembeli'] : $r['nama_agen'];

    $s = $pdo->prepare('SELECT x.*, pu.nama AS nama_pengusul FROM usulan x JOIN users pu ON pu.id_user = x.id_pengusul WHERE x.id_permintaan = ? AND x.jenis = ? ORDER BY x.id_usulan DESC');
    $s->execute([$r['id_permintaan'], $jenis]);
    $rows = $s->fetchAll();
    $pending = null;
    foreach ($rows as $x) {
        if ($x['status'] === 'Menunggu') {
            $pending = $x;
            break;
        }
    }
    $judul = $jenis === 'Jadwal' ? 'Jadwal Survei' : 'Negosiasi Harga';
    $harga_final = ($jenis === 'Harga' && $r['status'] === 'Harga Disepakati');
?>
    <div class="box">
        <h3 style="margin-top:0"><?= e($judul) ?></h3>
        <?php if ($jenis === 'Harga'): ?>
            <p class="mut">Harga listing: <b><?= rp($r['harga']) ?></b>
                <?php if ($r['harga_sepakat'] !== null): ?> · Harga disepakati: <b><?= rp($r['harga_sepakat']) ?></b><?php endif; ?></p>
        <?php endif; ?>

        <?php if ($jenis === 'Jadwal') neg_survei($pdo, $r, $u); ?>

        <?php if ($pending): ?>
            <?php if ((int)$pending['id_pengusul'] === $me): ?>
                <div class="alert">Anda mengusulkan <b><?= e(neg_label_nilai($pending)) ?></b>. Menunggu persetujuan <?= e($lawan) ?>.
                    <?= $pending['catatan'] ? '<br><span class="mut">Catatan: ' . e($pending['catatan']) . '</span>' : '' ?>
                    <div style="margin-top:8px"><?= neg_form_aksi('batal', (int)$pending['id_usulan'], 'Batalkan usulan', 'sec') ?></div>
                </div>
            <?php else: ?>
                <div class="alert"><b><?= e($pending['nama_pengusul']) ?></b> mengusulkan <b><?= e(neg_label_nilai($pending)) ?></b>.
                    <?= $pending['catatan'] ? '<br><span class="mut">Catatan: ' . e($pending['catatan']) . '</span>' : '' ?>
                    <div style="margin-top:8px">
                        <?= neg_form_aksi('setuju', (int)$pending['id_usulan'], 'Setujui', '', $jenis === 'Harga' ? 'Setujui harga ini?' : 'Setujui jadwal ini?') ?>
                        <?= neg_form_aksi('tolak', (int)$pending['id_usulan'], 'Tolak', 'del') ?>
                        <details class="counter">
                            <summary>Ajukan <?= $jenis === 'Jadwal' ? 'jadwal' : 'harga' ?> tandingan</summary>
                            <form class="row" method="post" style="margin-top:8px"><?= csrf() ?>
                                <input type="hidden" name="a" value="balas">
                                <input type="hidden" name="id_usulan" value="<?= (int)$pending['id_usulan'] ?>">
                                <?= neg_input($jenis) ?>
                                <input name="catatan" maxlength="255" placeholder="Catatan (opsional)">
                                <button>Kirim tandingan</button>
                            </form>
                        </details>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($tutup): ?>
            <p class="mut">Pengajuan sudah ditutup.</p>
        <?php elseif ($terjual): ?>
            <p class="mut">Properti sudah terjual.</p>
        <?php elseif ($harga_final): ?>
            <div class="alert ok">Harga sudah disepakati kedua pihak.</div>
        <?php else: ?>
            <form class="row" method="post"><?= csrf() ?>
                <input type="hidden" name="a" value="usul">
                <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                <?= neg_input($jenis) ?>
                <input name="catatan" maxlength="255" placeholder="Catatan (opsional)">
                <button>Ajukan <?= $jenis === 'Jadwal' ? 'jadwal' : 'harga' ?></button>
            </form>
        <?php endif; ?>

        <?php if ($rows): ?>
            <table style="margin-top:12px">
                <tr>
                    <th>Waktu</th>
                    <th>Pengusul</th>
                    <th><?= $jenis === 'Jadwal' ? 'Tanggal' : 'Harga' ?></th>
                    <th>Catatan</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($rows as $x): ?>
                    <tr>
                        <td><?= e(tgl($x['created_at'])) ?></td>
                        <td><?= (int)$x['id_pengusul'] === $me ? 'Anda' : e($x['nama_pengusul']) ?></td>
                        <td><?= e(neg_label_nilai($x)) ?></td>
                        <td><?= e($x['catatan'] ?? '') ?></td>
                        <td><span class="tag <?= e($x['status']) ?>"><?= e($x['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
<?php
}

// Jadwal survei yang sudah disetujui kedua pihak
function neg_survei(PDO $pdo, array $r, array $u): void
{
    $tutup = in_array($r['status'], STATUS_TUTUP, true);
    $s = $pdo->prepare('SELECT * FROM jadwal_survei WHERE id_permintaan = ? ORDER BY tanggal_survei');
    $s->execute([$r['id_permintaan']]);
    $rows = $s->fetchAll();
    if (!$rows) return;
?>
    <p class="mut" style="margin-bottom:4px">Jadwal terkonfirmasi</p>
    <table style="margin-bottom:12px">
        <?php foreach ($rows as $x): ?>
            <tr>
                <td><?= e(tgl($x['tanggal_survei'])) ?></td>
                <td><span class="tag <?= e($x['status_survei']) ?>"><?= e($x['status_survei']) ?></span></td>
                <td>
                    <?php if ($x['status_survei'] === 'Dikonfirmasi' && !$tutup): ?>
                        <?php foreach (($u['role'] === 'agen' ? ['Selesai', 'Dibatalkan'] : ['Dibatalkan']) as $to): ?>
                            <form method="post" class="inline" <?= $to === 'Dibatalkan' ? 'onsubmit="return confirm(\'Batalkan jadwal survei ini?\')"' : '' ?>><?= csrf() ?>
                                <input type="hidden" name="a" value="survei_status">
                                <input type="hidden" name="sid" value="<?= (int)$x['id_survei'] ?>">
                                <input type="hidden" name="ss" value="<?= e($to) ?>">
                                <button class="<?= $to === 'Dibatalkan' ? 'sec' : '' ?>"><?= $to === 'Selesai' ? 'Tandai selesai' : 'Batalkan' ?></button>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php
}

// Percakapan dua arah
function neg_chat(PDO $pdo, array $r, array $u): void
{
    $me = (int)$u['id'];
    $tutup = in_array($r['status'], STATUS_TUTUP, true);
    $s = $pdo->prepare('SELECT m.*, pg.nama FROM pesan m JOIN users pg ON pg.id_user = m.id_pengirim WHERE m.id_permintaan = ? ORDER BY m.id_pesan');
    $s->execute([$r['id_permintaan']]);
    $rows = $s->fetchAll();
?>
    <div class="box">
        <h3 style="margin-top:0">Percakapan</h3>
        <div class="chat">
            <?php if ($r['pesan']): ?>
                <div class="bub <?= (int)$r['id_user'] === $me ? 'me' : '' ?>">
                    <small><?= (int)$r['id_user'] === $me ? 'Anda' : e($r['nama_pembeli']) ?> · <?= e(tgl($r['tanggal'])) ?> · pesan awal</small>
                    <?= nl2br(e($r['pesan'])) ?>
                </div>
            <?php endif; ?>
            <?php foreach ($rows as $m): ?>
                <div class="bub <?= (int)$m['id_pengirim'] === $me ? 'me' : '' ?>">
                    <small><?= (int)$m['id_pengirim'] === $me ? 'Anda' : e($m['nama']) ?> · <?= e(tgl($m['created_at'])) ?></small>
                    <?= nl2br(e($m['isi'])) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$tutup): ?>
            <form class="f" method="post"><?= csrf() ?>
                <input type="hidden" name="a" value="pesan">
                <textarea name="isi" rows="2" maxlength="2000" required placeholder="Tulis pesan..."></textarea>
                <button>Kirim pesan</button>
            </form>
        <?php endif; ?>
    </div>
<?php
}

// Jumlah usulan yang menunggu tanggapan SAYA (untuk lencana notifikasi di daftar)
const SQL_PERLU_RESPONS = '(SELECT COUNT(*) FROM usulan x WHERE x.id_permintaan = r.id_permintaan AND x.status = "Menunggu" AND x.id_pengusul <> ?)';
