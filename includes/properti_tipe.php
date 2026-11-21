<?php
// Definisi atribut per tipe properti. Tambah tipe/atribut baru cukup di sini
// (untuk tipe baru, tambahkan juga nilainya di ENUM kolom properti.tipe).
//
// luas_tanah / luas_bangunan : apakah kolom luas tsb relevan untuk tipe ini
// spek: kunci => [label, jenis input, satuan, opsi (untuk select)]

const SERTIFIKAT = ['SHM (Sertifikat Hak Milik)', 'HGB (Hak Guna Bangunan)', 'SHSRS (Sertifikat Hak Satuan Rumah Susun)', 'AJB (Akta Jual Beli)', 'Lainnya'];

const TIPE_PROPERTI = [
    'Rumah' => [
        'luas_tanah' => true,
        'luas_bangunan' => true,
        'spek' => [
            'kamar_tidur'   => ['Kamar tidur', 'number', '', []],
            'kamar_mandi'   => ['Kamar mandi', 'number', '', []],
            'jumlah_lantai' => ['Jumlah lantai', 'number', '', []],
            'garasi'        => ['Garasi / carport', 'number', '', []],
            'daya_listrik'  => ['Daya listrik', 'number', 'watt', []],
            'kondisi'       => ['Kondisi', 'select', '', ['Baru', 'Bekas - terawat', 'Perlu renovasi']],
        ],
    ],
    'Ruko' => [
        'luas_tanah' => true,
        'luas_bangunan' => true,
        'spek' => [
            'jumlah_lantai' => ['Jumlah lantai', 'number', '', []],
            'lebar_depan'   => ['Lebar', 'number', 'm', []],
            'kamar_mandi'   => ['Kamar mandi', 'number', '', []],
            'daya_listrik'  => ['Daya listrik', 'number', 'watt', []],
            'akses_jalan'   => ['Akses jalan', 'select', '', ['Jalan utama', 'Jalan lingkungan', 'Dalam kompleks']],
            'kondisi'       => ['Kondisi', 'select', '', ['Baru', 'Bekas - terawat', 'Perlu renovasi']],
        ],
    ],
    'Tanah' => [
        'luas_tanah' => true,
        'luas_bangunan' => false,
        'spek' => [
            'peruntukan'  => ['Peruntukan', 'select', '', ['Perumahan', 'Komersial', 'Industri', 'Pertanian / kebun']],
            'lebar_depan' => ['Lebar', 'number', 'm', []],
            'kontur'      => ['Kontur tanah', 'select', '', ['Datar', 'Miring', 'Berbukit']],
            'akses_jalan' => ['Akses jalan', 'select', '', ['Jalan utama', 'Jalan lingkungan', 'Jalan setapak']],
        ],
    ],
    'Apartemen' => [
        'luas_tanah' => false,
        'luas_bangunan' => true, // dipakai sebagai luas unit
        'spek' => [
            'nama_gedung' => ['Nama gedung / tower', 'text', '', []],
            'lantai_unit' => ['Lantai unit', 'number', '', []],
            'kamar_tidur' => ['Kamar tidur', 'number', '', []],
            'kamar_mandi' => ['Kamar mandi', 'number', '', []],
            'furnishing'  => ['Furnishing', 'select', '', ['Unfurnished', 'Semi furnished', 'Fully furnished']],
            'parkir'      => ['Parkir', 'number', '', []],
        ],
    ],
];

// Label kolom luas bisa berbeda per tipe
function label_luas(string $tipe, string $kolom): string
{
    if ($kolom === 'luas_bangunan') return $tipe === 'Apartemen' ? 'Luas unit' : 'Luas bangunan';
    return 'Luas tanah';
}

// Ambil atribut khusus sebuah properti: ['kamar_tidur' => '3', ...]
function spek_ambil(PDO $pdo, int $id): array
{
    $s = $pdo->prepare('SELECT kunci, nilai FROM properti_spesifikasi WHERE id_properti = ?');
    $s->execute([$id]);
    return $s->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Ubah atribut jadi daftar [label, nilai tampil] sesuai urutan definisi tipe
function spek_tampil(string $tipe, array $spek): array
{
    $out = [];
    foreach (TIPE_PROPERTI[$tipe]['spek'] ?? [] as $k => [$label,, $satuan]) {
        if (isset($spek[$k]) && $spek[$k] !== '') {
            $out[] = [$label, $spek[$k] . ($satuan ? ' ' . $satuan : '')];
        }
    }
    return $out;
}

// Ringkasan satu baris untuk kartu di beranda
function ringkas_luas(array $r): string
{
    $c = TIPE_PROPERTI[$r['tipe']] ?? ['luas_tanah' => true, 'luas_bangunan' => true];
    $p = [];
    if ($c['luas_tanah'] && $r['luas_tanah'] !== null) $p[] = 'LT ' . $r['luas_tanah'] . ' m²';
    if ($c['luas_bangunan'] && $r['luas_bangunan'] !== null) {
        $p[] = ($r['tipe'] === 'Apartemen' ? 'Unit ' : 'LB ') . $r['luas_bangunan'] . ' m²';
    }
    return implode(' · ', $p);
}

// Validasi & bersihkan input atribut dari form untuk tipe tertentu.
// Hanya kunci yang terdaftar untuk tipe itu yang diterima.
function spek_bersihkan(string $tipe, array $post): array
{
    $out = [];
    foreach (TIPE_PROPERTI[$tipe]['spek'] ?? [] as $k => [, $jenis,, $opsi]) {
        $v = trim((string)($post['spek'][$k] ?? ''));
        if ($v === '') continue;
        if ($jenis === 'number') {
            if (!ctype_digit($v) || (int)$v > 1000000) continue;
        } elseif ($jenis === 'select') {
            if (!in_array($v, $opsi, true)) continue;
        } else {
            $v = mb_substr($v, 0, 100);
        }
        $out[$k] = $v;
    }
    return $out;
}
