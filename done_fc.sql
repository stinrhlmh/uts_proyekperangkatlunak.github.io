-- =====================================================
-- D'one Fried Chicken - Database Schema
-- Import via: phpMyAdmin > Import atau
--             mysql -u root -p < done_fc.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS done_fc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE done_fc;

-- ── Tabel Users (Login) ──────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,  -- bcrypt hash
  nama       VARCHAR(100) NOT NULL,
  role       ENUM('Admin','Kasir') NOT NULL DEFAULT 'Kasir',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password default: admin123 & kasir123 (bcrypt)
INSERT INTO users (username, password, nama, role) VALUES
  ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'Admin'),
  ('kasir', '$2y$10$TKh8H1.PftqtH8O0KBQYAOWF5a7msq8b1X3GJilqRvjd1sWUPqMGS', 'Kasir', 'Kasir');
-- Note: password di atas adalah hash dari 'password' (default Laravel).
-- Gunakan endpoint /api/auth.php?action=seed_users untuk set password yang benar.

-- ── Tabel Barang Masuk ───────────────────────────
CREATE TABLE IF NOT EXISTS barang_masuk (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  tanggal      DATE         NOT NULL,
  nama_barang  VARCHAR(150) NOT NULL,
  kategori     VARCHAR(50)  NOT NULL,
  jumlah       DECIMAL(10,2) NOT NULL,
  satuan       VARCHAR(20)  NOT NULL,
  harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0,
  total        DECIMAL(15,2) NOT NULL DEFAULT 0,
  supplier     VARCHAR(100),
  keterangan   TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Barang Keluar (Penjualan) ──────────────
CREATE TABLE IF NOT EXISTS barang_keluar (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  tanggal      DATE         NOT NULL,
  nama_produk  VARCHAR(150) NOT NULL,
  jumlah       INT          NOT NULL,
  harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0,
  total        DECIMAL(15,2) NOT NULL DEFAULT 0,
  keterangan   TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Pengeluaran ────────────────────────────
CREATE TABLE IF NOT EXISTS pengeluaran (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  tanggal    DATE         NOT NULL,
  kategori   VARCHAR(50)  NOT NULL,
  deskripsi  VARCHAR(200) NOT NULL,
  jumlah     DECIMAL(10,2) NOT NULL DEFAULT 1,
  nominal    DECIMAL(15,2) NOT NULL DEFAULT 0,
  total      DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Laporan Harian ─────────────────────────
CREATE TABLE IF NOT EXISTS laporan_harian (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  tanggal             DATE NOT NULL UNIQUE,
  jam_buka            TIME,
  jam_tutup           TIME,
  total_pendapatan    DECIMAL(15,2) NOT NULL DEFAULT 0,
  hpp                 DECIMAL(15,2) NOT NULL DEFAULT 0,
  pengeluaran_ops     DECIMAL(15,2) NOT NULL DEFAULT 0,
  pengeluaran_gaji    DECIMAL(15,2) NOT NULL DEFAULT 0,
  pengeluaran_lain    DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_pengeluaran   DECIMAL(15,2) NOT NULL DEFAULT 0,
  laba_kotor          DECIMAL(15,2) NOT NULL DEFAULT 0,
  laba_bersih         DECIMAL(15,2) NOT NULL DEFAULT 0,
  catatan             TEXT,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tabel Detail Produk per Laporan ─────────────
CREATE TABLE IF NOT EXISTS laporan_produk (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  laporan_id   INT NOT NULL,
  nama         VARCHAR(150) NOT NULL,
  qty          INT          NOT NULL DEFAULT 0,
  harga        DECIMAL(15,2) NOT NULL DEFAULT 0,
  subtotal     DECIMAL(15,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (laporan_id) REFERENCES laporan_harian(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Demo Data ────────────────────────────────────
INSERT INTO barang_masuk (tanggal, nama_barang, kategori, jumlah, satuan, harga_satuan, total, supplier) VALUES
  (CURDATE(), 'Ayam Potong',   'Bahan Baku', 30, 'kg',    35000, 1050000, 'Pak Budi'),
  (CURDATE(), 'Tepung Terigu', 'Bahan Baku', 10, 'kg',    12000,  120000, 'Toko Sari'),
  (DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'Minyak Goreng', 'Bahan Baku', 15, 'liter', 18000, 270000, 'Toko Sari'),
  (DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'Bumbu Rempah',  'Bumbu',      5,  'kg',    25000, 125000, 'Pak Budi'),
  (DATE_SUB(CURDATE(),INTERVAL 2 DAY), 'Kemasan Box',   'Kemasan',    200,'pcs',    1200, 240000, 'CV Karya');

INSERT INTO barang_keluar (tanggal, nama_produk, jumlah, harga_satuan, total) VALUES
  (DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'FC Original 1 Pcs',  70, 12000,  840000),
  (DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'FC Spicy 1 Pcs',     55, 13000,  715000),
  (DATE_SUB(CURDATE(),INTERVAL 2 DAY), 'Paket Hemat 5 Pcs',  18, 55000,  990000);

INSERT INTO pengeluaran (tanggal, kategori, deskripsi, jumlah, nominal, total) VALUES
  (CURDATE(),                          'Operasional', 'Elpiji 12 kg',              1, 210000, 210000),
  (CURDATE(),                          'Gaji',        'Upah harian karyawan',      2, 100000, 200000),
  (DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'Operasional', 'Plastik & kantong',       100,    500,  50000),
  (DATE_SUB(CURDATE(),INTERVAL 2 DAY), 'Operasional', 'Sabun & pembersih',         1,  35000,  35000);
