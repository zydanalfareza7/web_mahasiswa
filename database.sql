-- Database Schema untuk Web Mahasiswa & Absensi (StudentHub)
-- Import file ini ke phpMyAdmin di cPanel InfinityFree

SET FOREIGN_KEY_CHECKS=0;

-- 1. Tabel users (Untuk Akun Login Admin)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Akun default: username = admin, password = admin
INSERT INTO `users` (`username`, `password`) VALUES
('admin', 'admin')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- 2. Tabel tb_mhs (Data Master Mahasiswa)
CREATE TABLE IF NOT EXISTS `tb_mhs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `NPM` varchar(20) NOT NULL UNIQUE,
  `NAMA` varchar(100) NOT NULL,
  `KELAS` varchar(20) NOT NULL,
  `JURUSAN` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel tb_absensi (Riwayat Absensi Mahasiswa dengan Foto & Lokasi GPS)
CREATE TABLE IF NOT EXISTS `tb_absensi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `npm` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Hadir',
  `foto` varchar(255) DEFAULT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- JIKA DATABASE SUDAH ADA SEBELUMNYA, JALANKAN PERINTAH ALTER INI DI PHPMYADMIN:
-- ALTER TABLE `tb_absensi` ADD COLUMN `foto` varchar(255) DEFAULT NULL AFTER `status`;
-- ALTER TABLE `tb_absensi` ADD COLUMN `latitude` varchar(50) DEFAULT NULL AFTER `foto`;
-- ALTER TABLE `tb_absensi` ADD COLUMN `longitude` varchar(50) DEFAULT NULL AFTER `latitude`;

SET FOREIGN_KEY_CHECKS=1;
