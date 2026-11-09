<?php
// =====================================================
// D'one Fried Chicken - Setup Otomatis
// Buka sekali: http://localhost/inventory/setup.php
// =====================================================
require_once __DIR__ . '/api/config.php';

// Nonaktifkan output buffer agar bisa streaming
@ob_end_flush();

$log = [];
$errors = [];

function logOk($msg)  { global $log;    $log[]    = ['ok',   $msg]; }
function logFail($msg){ global $errors; $errors[] = ['fail', $msg]; }
function logInfo($msg){ global $log;    $log[]    = ['info', $msg]; }

// ── 1. Cek koneksi DB ─────────────────────────────
try {
    $db = getDB();
    logOk('Koneksi MySQL berhasil (database: ' . DB_NAME . ')');
} catch (Exception $e) {
    // Database mungkin belum ada, coba buat
    try {
        $dsn2 = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
        $pdo2 = new PDO($dsn2, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo2->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        logOk('Database "' . DB_NAME . '" berhasil dibuat');
        $db = getDB();
    } catch (Exception $e2) {
        logFail('Tidak bisa konek MySQL: ' . $e2->getMessage());
        logFail('Pastikan XAMPP MySQL sudah nyala, dan cek DB_USER/DB_PASS di api/config.php');
    }
}

// ── 2. Buat semua tabel ───────────────────────────
if (isset($db)) {
    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(50)  NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            nama       VARCHAR(100) NOT NULL,
            role       ENUM('Admin','Kasir') NOT NULL DEFAULT 'Kasir',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'barang_masuk' => "CREATE TABLE IF NOT EXISTS barang_masuk (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            tanggal      DATE NOT NULL,
            nama_barang  VARCHAR(150) NOT NULL,
            kategori     VARCHAR(50)  NOT NULL,
            jumlah       DECIMAL(10,2) NOT NULL,
            satuan       VARCHAR(20)  NOT NULL,
            harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0,
            total        DECIMAL(15,2) NOT NULL DEFAULT 0,
            supplier     VARCHAR(100),
            keterangan   TEXT,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'barang_keluar' => "CREATE TABLE IF NOT EXISTS barang_keluar (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            tanggal      DATE NOT NULL,
            nama_produk  VARCHAR(150) NOT NULL,
            jumlah       INT NOT NULL,
            harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0,
            total        DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan   TEXT,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'pengeluaran' => "CREATE TABLE IF NOT EXISTS pengeluaran (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            tanggal    DATE NOT NULL,
            kategori   VARCHAR(50) NOT NULL,
            deskripsi  VARCHAR(200) NOT NULL,
            jumlah     DECIMAL(10,2) NOT NULL DEFAULT 1,
            nominal    DECIMAL(15,2) NOT NULL DEFAULT 0,
            total      DECIMAL(15,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'laporan_harian' => "CREATE TABLE IF NOT EXISTS laporan_harian (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            tanggal           DATE NOT NULL UNIQUE,
            jam_buka          TIME,
            jam_tutup         TIME,
            total_pendapatan  DECIMAL(15,2) NOT NULL DEFAULT 0,
            hpp               DECIMAL(15,2) NOT NULL DEFAULT 0,
            pengeluaran_ops   DECIMAL(15,2) NOT NULL DEFAULT 0,
            pengeluaran_gaji  DECIMAL(15,2) NOT NULL DEFAULT 0,
            pengeluaran_lain  DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_pengeluaran DECIMAL(15,2) NOT NULL DEFAULT 0,
            laba_kotor        DECIMAL(15,2) NOT NULL DEFAULT 0,
            laba_bersih       DECIMAL(15,2) NOT NULL DEFAULT 0,
            catatan           TEXT,
            created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'laporan_produk' => "CREATE TABLE IF NOT EXISTS laporan_produk (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            laporan_id INT NOT NULL,
            nama       VARCHAR(150) NOT NULL,
            qty        INT NOT NULL DEFAULT 0,
            harga      DECIMAL(15,2) NOT NULL DEFAULT 0,
            subtotal   DECIMAL(15,2) NOT NULL DEFAULT 0,
            FOREIGN KEY (laporan_id) REFERENCES laporan_harian(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tables as $name => $sql) {
        try {
            $db->exec($sql);
            logOk("Tabel `$name` siap");
        } catch (Exception $e) {
            logFail("Gagal buat tabel `$name`: " . $e->getMessage());
        }
    }

    // ── 3. Insert / update users dengan password benar ──
    $users = [
        ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'Admin'],
        ['kasir', password_hash('kasir123', PASSWORD_DEFAULT), 'Kasir',         'Kasir'],
    ];
    foreach ($users as [$uname, $hash, $nama, $role]) {
        try {
            $exists = $db->prepare('SELECT id FROM users WHERE username = ?');
            $exists->execute([$uname]);
            if ($exists->fetchColumn()) {
                $db->prepare('UPDATE users SET password=?, nama=?, role=? WHERE username=?')
                   ->execute([$hash, $nama, $role, $uname]);
                logOk("User '$uname' → password diperbarui");
            } else {
                $db->prepare('INSERT INTO users (username,password,nama,role) VALUES (?,?,?,?)')
                   ->execute([$uname, $hash, $nama, $role]);
                logOk("User '$uname' → berhasil dibuat");
            }
        } catch (Exception $e) {
            logFail("Gagal set user '$uname': " . $e->getMessage());
        }
    }
}

$allOk = empty($errors);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Setup – D'one Fried Chicken</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif;background:#fff8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
    .card{background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(230,92,0,.15);padding:32px 36px;width:100%;max-width:540px}
    .brand{text-align:center;margin-bottom:24px}
    .brand .icon{font-size:48px}
    .brand h1{font-size:22px;font-weight:800;color:#e65c00;margin-top:8px}
    .brand p{font-size:12px;color:#7a6050}
    .item{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px dashed #f0d9c8;font-size:13px}
    .item:last-child{border:none}
    .dot{width:22px;height:22px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;margin-top:1px}
    .ok  .dot{background:#e8f5e9;color:#2e7d32}
    .fail .dot{background:#ffebee;color:#c62828}
    .info .dot{background:#e3f2fd;color:#1565c0}
    .result{margin-top:20px;padding:14px 18px;border-radius:10px;font-size:14px;font-weight:700;text-align:center}
    .result.ok  {background:#e8f5e9;color:#2e7d32;border:2px solid #a5d6a7}
    .result.fail{background:#ffebee;color:#c62828;border:2px solid #ef9a9a}
    .btn{display:block;width:100%;padding:13px;border-radius:8px;border:none;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;text-align:center;margin-top:12px}
    .btn-orange{background:#e65c00;color:#fff}
    .btn-orange:hover{background:#bf4a00}
    .btn-gray{background:#f0e8e0;color:#7a6050}
    .btn-gray:hover{background:#e0d0c0}
    h3{font-size:13px;color:#7a6050;text-transform:uppercase;letter-spacing:.05em;margin:16px 0 8px}
  </style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="icon">🍗</div>
    <h1>D'one Fried Chicken</h1>
    <p>Setup Otomatis Database</p>
  </div>

  <h3>📋 Hasil Setup</h3>
  <?php foreach ($log as [$type, $msg]): ?>
  <div class="item <?= $type ?>">
    <div class="dot"><?= $type === 'ok' ? '✓' : ($type === 'fail' ? '✗' : 'i') ?></div>
    <div><?= htmlspecialchars($msg) ?></div>
  </div>
  <?php endforeach; ?>
  <?php foreach ($errors as [$type, $msg]): ?>
  <div class="item fail">
    <div class="dot">✗</div>
    <div><?= htmlspecialchars($msg) ?></div>
  </div>
  <?php endforeach; ?>

  <div class="result <?= $allOk ? 'ok' : 'fail' ?>">
    <?= $allOk
      ? '✅ Setup selesai! Semua komponen siap.'
      : '❌ Ada error. Baca pesan di atas dan perbaiki.' ?>
  </div>

  <?php if ($allOk): ?>
  <a href="login.html" class="btn btn-orange">🚀 Lanjut ke Halaman Login</a>
  <?php else: ?>
  <a href="setup.php" class="btn btn-gray">🔄 Coba Lagi</a>
  <?php endif; ?>

  <div style="margin-top:16px;padding:12px;background:#fff8f0;border-radius:8px;font-size:12px;color:#7a6050;border:1px dashed #f0d9c8">
    💡 <strong>Akun default:</strong><br>
    Username: <strong>admin</strong> → Password: <strong>admin123</strong><br>
    Username: <strong>kasir</strong> → Password: <strong>kasir123</strong>
  </div>
</div>
</body>
</html>
