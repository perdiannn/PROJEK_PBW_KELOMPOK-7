-- ========================================================
-- SISTEM INFORMASI PENJUALAN PROPERTI (SIPRO)
-- Database: sipro (MySQL / MariaDB via Laragon 6)
-- Arsitektur Terpadu 3 Aktor: Admin, Agen, Pembeli
-- Dilengkapi Komunikasi Dua Arah & Negosiasi Survei/Harga
-- ========================================================

CREATE DATABASE IF NOT EXISTS sipro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sipro;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS transaksi_penjualan, tindak_lanjut, pesan, jadwal_survei, usulan, permintaan, foto_properti, properti_spesifikasi, properti, users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. TABEL PENGGUNA TERPUSAT (RBAC)
CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'agen', 'pembeli') NOT NULL DEFAULT 'pembeli',
    telepon VARCHAR(25) NULL,
    status_akun ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. TABEL KATALOG PROPERTI
CREATE TABLE properti (
    id_properti INT AUTO_INCREMENT PRIMARY KEY,
    id_agen INT NOT NULL,
    judul VARCHAR(150) NOT NULL,
    deskripsi TEXT NULL,
    harga BIGINT UNSIGNED NOT NULL,
    lokasi VARCHAR(180) NOT NULL,
    tipe ENUM('Rumah', 'Ruko', 'Tanah', 'Apartemen') NOT NULL,
    luas_tanah DECIMAL(12,2) NULL,
    luas_bangunan DECIMAL(12,2) NULL,
    sertifikat VARCHAR(60) NULL,
    status ENUM('Tersedia', 'Dalam Negosiasi', 'Terjual') NOT NULL DEFAULT 'Tersedia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_properti_harga CHECK (harga > 0),
    FOREIGN KEY (id_agen) REFERENCES users(id_user) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 3. TABEL SPESIFIKASI DINAMIS PROPERTI
CREATE TABLE properti_spesifikasi (
    id_spek INT AUTO_INCREMENT PRIMARY KEY,
    id_properti INT NOT NULL,
    kunci VARCHAR(60) NOT NULL,
    nilai VARCHAR(150) NOT NULL,
    UNIQUE KEY uq_spek (id_properti, kunci),
    FOREIGN KEY (id_properti) REFERENCES properti(id_properti) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. TABEL GALERI FOTO PROPERTI
CREATE TABLE foto_properti (
    id_foto INT AUTO_INCREMENT PRIMARY KEY,
    id_properti INT NOT NULL,
    nama_file VARCHAR(120) NOT NULL,
    FOREIGN KEY (id_properti) REFERENCES properti(id_properti) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. TABEL PERMINTAAN / TIKET PENGAJUAN MINAT PEMBELI
CREATE TABLE permintaan (
    id_permintaan INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_properti INT NOT NULL,
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pesan TEXT NULL,
    status ENUM('Diajukan', 'Dihubungi', 'Menunggu Persetujuan Jadwal', 'Survei Dijadwalkan', 'Menunggu Persetujuan Harga', 'Negosiasi Harga', 'Harga Disepakati', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Diajukan',
    harga_sepakat BIGINT UNSIGNED NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON UPDATE CASCADE,
    FOREIGN KEY (id_properti) REFERENCES properti(id_properti) ON DELETE CASCADE,
    INDEX idx_permintaan_user (id_user),
    INDEX idx_permintaan_properti (id_properti)
) ENGINE=InnoDB;

-- 6. TABEL USULAN JADWAL SURVEI & NEGOSIASI HARGA
CREATE TABLE usulan (
    id_usulan INT AUTO_INCREMENT PRIMARY KEY,
    id_permintaan INT NOT NULL,
    jenis ENUM('Jadwal', 'Harga') NOT NULL,
    id_pengusul INT NOT NULL,
    tanggal_usulan DATETIME NULL,
    harga_usulan BIGINT UNSIGNED NULL,
    catatan VARCHAR(255) NULL,
    status ENUM('Menunggu', 'Disetujui', 'Ditolak', 'Dibatalkan') NOT NULL DEFAULT 'Menunggu',
    id_induk INT NULL,
    responded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    CONSTRAINT chk_usulan CHECK (
        (jenis = 'Jadwal' AND tanggal_usulan IS NOT NULL AND harga_usulan IS NULL) OR
        (jenis = 'Harga' AND harga_usulan IS NOT NULL AND harga_usulan > 0 AND tanggal_usulan IS NULL)
    ),
    FOREIGN KEY (id_permintaan) REFERENCES permintaan(id_permintaan) ON DELETE CASCADE,
    FOREIGN KEY (id_pengusul) REFERENCES users(id_user) ON UPDATE CASCADE,
    FOREIGN KEY (id_induk) REFERENCES usulan(id_usulan) ON DELETE SET NULL,
    FOREIGN KEY (responded_by) REFERENCES users(id_user) ON UPDATE CASCADE,
    INDEX idx_usulan_active (id_permintaan, jenis, status)
) ENGINE=InnoDB;

-- 7. TABEL JADWAL SURVEI RESMI
CREATE TABLE jadwal_survei (
    id_survei INT AUTO_INCREMENT PRIMARY KEY,
    id_permintaan INT NOT NULL,
    id_usulan INT NOT NULL UNIQUE,
    tanggal_survei DATETIME NOT NULL,
    status_survei ENUM('Dijadwalkan', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Dijadwalkan',
    FOREIGN KEY (id_permintaan) REFERENCES permintaan(id_permintaan) ON DELETE CASCADE,
    FOREIGN KEY (id_usulan) REFERENCES usulan(id_usulan) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. TABEL PESAN / CHAT DUA ARAH PEMBELI <-> AGEN
CREATE TABLE pesan (
    id_pesan INT AUTO_INCREMENT PRIMARY KEY,
    id_permintaan INT NOT NULL,
    id_pengirim INT NOT NULL,
    isi TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_permintaan) REFERENCES permintaan(id_permintaan) ON DELETE CASCADE,
    FOREIGN KEY (id_pengirim) REFERENCES users(id_user) ON UPDATE CASCADE,
    INDEX idx_pesan (id_permintaan, created_at)
) ENGINE=InnoDB;

-- 9. TABEL CATATAN TINDAK LANJUT INTERNAL AGEN (CRM)
CREATE TABLE tindak_lanjut (
    id_tindak_lanjut INT AUTO_INCREMENT PRIMARY KEY,
    id_permintaan INT NOT NULL,
    id_agen INT NOT NULL,
    catatan TEXT NOT NULL,
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_permintaan) REFERENCES permintaan(id_permintaan) ON DELETE CASCADE,
    FOREIGN KEY (id_agen) REFERENCES users(id_user) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 10. TABEL TRANSAKSI PENJUALAN FINAL
CREATE TABLE transaksi_penjualan (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    id_properti INT NOT NULL,
    id_pembeli INT NOT NULL,
    id_permintaan INT NOT NULL UNIQUE,
    tanggal_transaksi DATE NOT NULL,
    harga_final BIGINT UNSIGNED NOT NULL,
    status ENUM('Proses', 'Selesai', 'Batal') NOT NULL DEFAULT 'Selesai',
    UNIQUE KEY uq_transaksi_properti (id_properti),
    FOREIGN KEY (id_properti) REFERENCES properti(id_properti),
    FOREIGN KEY (id_pembeli) REFERENCES users(id_user),
    FOREIGN KEY (id_permintaan) REFERENCES permintaan(id_permintaan)
) ENGINE=InnoDB;


-- ========================================================
-- DATA AWAL (SEED DATA) DEMONSTRASI & PENGUJIAN
-- ========================================================

-- Akun Default (Password: admin123, agen123, pembeli123)
INSERT INTO users (id_user, nama, email, password, role, telepon, status_akun) VALUES
(1, 'Administrator SIPRO', 'admin@sipro.com', '$2y$10$CxgMyifMAmStRdOeuQEXzeRJ55XLEvktdJef8djf7Wt8pta1R94LC', 'admin', '081234567890', 'Aktif'),
(2, 'Budi Santoso (Agen)', 'budi.agen@sipro.com', '$2y$10$ZqgFC3EB.bo0npJMnWrbX.pfkkclQRoBjwq8XaEV.Mn27q3wIbMq2', 'agen', '081298765432', 'Aktif'),
(3, 'Siti Rahma (Agen)', 'siti.agen@sipro.com', '$2y$10$ZqgFC3EB.bo0npJMnWrbX.pfkkclQRoBjwq8XaEV.Mn27q3wIbMq2', 'agen', '081311223344', 'Aktif'),
(4, 'Andi Wijaya (Pembeli)', 'andi.pembeli@gmail.com', '$2y$10$naZlkF2ALMDZ4qohMz8wLOglmZfuBB/EjEFV0F1nOd2wu3RS5wcay', 'pembeli', '085678901234', 'Aktif'),
(5, 'Dewi Lestari (Pembeli)', 'dewi.pembeli@gmail.com', '$2y$10$naZlkF2ALMDZ4qohMz8wLOglmZfuBB/EjEFV0F1nOd2wu3RS5wcay', 'pembeli', '087812345678', 'Aktif');

-- Data Katalog Properti
INSERT INTO properti (id_properti, id_agen, judul, deskripsi, harga, lokasi, tipe, luas_tanah, luas_bangunan, sertifikat, status) VALUES
(1, 2, 'Rumah Minimalis Modern Cluster Asri', 'Rumah siap huni dengan lingkungan asri dan sistem keamanan 24 jam. Lokasi sangat strategis dekat dengan pusat perbelanjaan, sekolah unggulan, dan akses jalan tol utama.', 750000000, 'Kedaton, Bandar Lampung', 'Rumah', 120.00, 90.00, 'SHM - Sertifikat Hak Milik', 'Tersedia'),
(2, 2, 'Ruko 3 Lantai Strategis Sentra Bisnis', 'Ruko komersial cocok untuk kantor cabang, minimarket, kafe kekinian, atau klinik kesehatan. Area parkir luas dengan lalu lintas ramai setiap hari.', 1650000000, 'Tanjung Karang Pusat, Bandar Lampung', 'Ruko', 150.00, 300.00, 'HGB - Hak Guna Bangunan', 'Dalam Negosiasi'),
(3, 3, 'Apartemen Mewah Grand City View', 'Unit apartemen studio fully furnished dengan pemandangan kota yang menakjubkan. Dilengkapi fasilitas kolam renang infinity, pusat kebugaran (gym), dan kartu akses aman.', 520000000, 'Teluk Betung Selatan, Bandar Lampung', 'Apartemen', 36.00, 36.00, 'Strata Title', 'Tersedia'),
(4, 3, 'Tanah Kavling Siap Bangun Kawasan Elit', 'Tanah kavling datar bebas banjir, sangat ideal untuk dibangun villa keluarga atau investasi properti jangka panjang yang bernilai tinggi.', 350000000, 'Sukarame, Bandar Lampung', 'Tanah', 200.00, 0.00, 'SHM - Sertifikat Hak Milik', 'Terjual');

-- Spesifikasi Kustom Properti
INSERT INTO properti_spesifikasi (id_spek, id_properti, kunci, nilai) VALUES
(1, 1, 'Kamar Tidur', '3'),
(2, 1, 'Kamar Mandi', '2'),
(3, 1, 'Daya Listrik', '2200 VA'),
(4, 1, 'Sumber Air', 'Sumur Bor & PDAM'),
(5, 2, 'Kamar Mandi', '3'),
(6, 2, 'Daya Listrik', '4400 VA'),
(7, 3, 'Kamar Tidur', '1'),
(8, 3, 'Kamar Mandi', '1'),
(9, 3, 'Lantai', 'Lantai 12');

-- Referensi Foto Properti (Sesuai File di uploads/)
INSERT INTO foto_properti (id_foto, id_properti, nama_file) VALUES
(1, 1, '108c34d17bd3a283.jpg'),
(2, 1, '33a794f62a67dae2.jpg'),
(3, 1, '774d252b3d90292d.jpg'),
(4, 2, '9bba1ba28ff09af6.jpg'),
(5, 2, 'b0a5c9c4c5d84c19.jpg'),
(6, 3, 'ba3d331487aff57c.jpg'),
(7, 3, 'bd0d69b2958ca46a.jpg'),
(8, 4, 'c032f7fcd7631532.jpg'),
(9, 4, 'f7cf6257d814e75f.jpg');

-- Data Tiket Pengajuan Minat Pembeli
INSERT INTO permintaan (id_permintaan, id_user, id_properti, tanggal, pesan, status, harga_sepakat) VALUES
(1, 4, 2, '2026-11-20 10:15:00', 'Halo Bapak Budi, saya tertarik dengan unit ruko ini untuk usaha retail. Apakah bisa dijadwalkan survei ke lokasi?', 'Survei Dijadwalkan', NULL),
(2, 5, 4, '2026-11-14 09:00:00', 'Berminat membeli tanah kavling ini secara tunai.', 'Selesai', 350000000);

-- Data Usulan Jadwal Survei
INSERT INTO usulan (id_usulan, id_permintaan, jenis, id_pengusul, tanggal_usulan, harga_usulan, catatan, status, responded_by, created_at, responded_at) VALUES
(1, 1, 'Jadwal', 2, '2026-11-25 14:00:00', NULL, 'Bisa Pak Andi, bagaimana jika survei hari Rabu jam 14:00?', 'Disetujui', 4, '2026-11-20 11:00:00', '2026-11-20 11:30:00');

-- Data Jadwal Survei Resmi Disepakati
INSERT INTO jadwal_survei (id_survei, id_permintaan, id_usulan, tanggal_survei, status_survei) VALUES
(1, 1, 1, '2026-11-25 14:00:00', 'Dijadwalkan');

-- Data Percakapan / Chat Dua Arah
INSERT INTO pesan (id_pesan, id_permintaan, id_pengirim, isi, created_at) VALUES
(1, 1, 4, 'Halo Pak Budi, saya tertarik dengan unit ruko 3 lantai ini.', '2026-11-20 10:15:00'),
(2, 1, 2, 'Halo Pak Andi! Terima kasih atas minatnya. Ruko ini posisinya sangat strategis dan siap pakai.', '2026-11-20 10:45:00'),
(3, 1, 4, 'Kira-kira kapan waktu yang tepat untuk kita cek fisik bangunannya bersama?', '2026-11-20 10:50:00'),
(4, 1, 2, 'Saya sudah ajukan usulan jadwal survei untuk hari Rabu tgl 25 jam 14:00 WIB, silakan disetujui jika cocok.', '2026-11-20 11:01:00'),
(5, 1, 4, 'Baik Pak Budi, sudah saya setujui jadwalnya. Sampai bertemu hari Rabu.', '2026-11-20 11:31:00');

-- Data Catatan Tindak Lanjut Agen
INSERT INTO tindak_lanjut (id_tindak_lanjut, id_permintaan, id_agen, catatan, tanggal) VALUES
(1, 1, 2, 'Calon pembeli prospektif, berminat untuk gerai retail. Jadwal survei fisik sudah disepakati.', '2026-11-20 11:35:00');

-- Data Riwayat Transaksi Selesai
INSERT INTO transaksi_penjualan (id_transaksi, id_properti, id_pembeli, id_permintaan, tanggal_transaksi, harga_final, status) VALUES
(1, 4, 5, 2, '2026-11-15', 350000000, 'Selesai');
