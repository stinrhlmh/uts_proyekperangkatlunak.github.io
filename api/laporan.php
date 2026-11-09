<?php
// =====================================================
// D'one Fried Chicken - Laporan Harian API
// GET    /api/laporan.php                  → semua laporan
// GET    /api/laporan.php?tanggal=...      → laporan 1 hari
// GET    /api/laporan.php?mode=weekly      → ringkasan 7 hari
// GET    /api/laporan.php?bulan=YYYY-MM    → filter bulan
// POST   /api/laporan.php                  → simpan/update laporan
// DELETE /api/laporan.php?id=...           → hapus laporan
// =====================================================
require_once __DIR__ . '/config.php';
setCorsHeaders();
session_start();

if (!isset($_SESSION['fc_user'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ── Helper: ambil produk untuk 1 laporan ──────────
function getProduk(PDO $db, int $laporan_id): array {
    $st = $db->prepare('SELECT * FROM laporan_produk WHERE laporan_id = ? ORDER BY id');
    $st->execute([$laporan_id]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['qty']      = (int)   $r['qty'];
        $r['harga']    = (float) $r['harga'];
        $r['subtotal'] = (float) $r['subtotal'];
    }
    return $rows;
}

// ── Helper: format laporan row ────────────────────
function formatLaporan(array $l): array {
    $fields = ['total_pendapatan','hpp','pengeluaran_ops','pengeluaran_gaji',
               'pengeluaran_lain','total_pengeluaran','laba_kotor','laba_bersih'];
    foreach ($fields as $f) $l[$f] = (float) $l[$f];
    return $l;
}

// ── GET ───────────────────────────────────────────
if ($method === 'GET') {
    $mode = $_GET['mode'] ?? '';

    // Ringkasan 7 hari untuk grafik
    if ($mode === 'weekly') {
        $st = $db->prepare("
            SELECT tanggal, total_pendapatan, laba_bersih, total_pengeluaran
            FROM laporan_harian
            WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            ORDER BY tanggal ASC
        ");
        $st->execute();
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['total_pendapatan']  = (float) $r['total_pendapatan'];
            $r['laba_bersih']       = (float) $r['laba_bersih'];
            $r['total_pengeluaran'] = (float) $r['total_pengeluaran'];
        }
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    // Filter tanggal tepat (1 hari)
    if (!empty($_GET['tanggal'])) {
        $st = $db->prepare('SELECT * FROM laporan_harian WHERE tanggal = ? LIMIT 1');
        $st->execute([$_GET['tanggal']]);
        $row = $st->fetch();
        if (!$row) jsonResponse(['success' => true, 'data' => null]);
        $row = formatLaporan($row);
        $row['produk'] = getProduk($db, (int) $row['id']);
        jsonResponse(['success' => true, 'data' => $row]);
    }

    // Filter bulan
    $sql    = 'SELECT * FROM laporan_harian';
    $params = [];
    if (!empty($_GET['bulan'])) {
        $sql    .= " WHERE DATE_FORMAT(tanggal,'%Y-%m') = ?";
        $params[] = $_GET['bulan'];
    }
    $sql .= ' ORDER BY tanggal DESC';

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    // Sertakan produk untuk setiap laporan
    foreach ($rows as &$r) {
        $r = formatLaporan($r);
        $r['produk'] = getProduk($db, (int) $r['id']);
    }
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ── POST (simpan / update laporan) ───────────────
if ($method === 'POST') {
    $b = getBody();

    if (empty($b['tanggal'])) {
        jsonResponse(['success' => false, 'message' => 'Tanggal wajib diisi.'], 400);
    }
    if (empty($b['produk']) || !is_array($b['produk'])) {
        jsonResponse(['success' => false, 'message' => 'Detail produk wajib diisi.'], 400);
    }

    $totalPendapatan = array_sum(array_column($b['produk'], 'subtotal'));
    $hpp     = (float) ($b['hpp']              ?? 0);
    $ops     = (float) ($b['pengeluaran_ops']  ?? 0);
    $gaji    = (float) ($b['pengeluaran_gaji'] ?? 0);
    $lain    = (float) ($b['pengeluaran_lain'] ?? 0);
    $totalPe = $hpp + $ops + $gaji + $lain;
    $labaKotor  = $totalPendapatan - $hpp;
    $labaBersih = $totalPendapatan - $totalPe;

    $db->beginTransaction();
    try {
        // Cek apakah laporan tanggal ini sudah ada
        $st  = $db->prepare('SELECT id FROM laporan_harian WHERE tanggal = ? LIMIT 1');
        $st->execute([$b['tanggal']]);
        $existing = $st->fetchColumn();

        if ($existing) {
            // Update
            $db->prepare("UPDATE laporan_harian SET
                jam_buka=?, jam_tutup=?,
                total_pendapatan=?, hpp=?,
                pengeluaran_ops=?, pengeluaran_gaji=?, pengeluaran_lain=?,
                total_pengeluaran=?, laba_kotor=?, laba_bersih=?, catatan=?
                WHERE id=?")
               ->execute([
                   $b['jam_buka'] ?? null, $b['jam_tutup'] ?? null,
                   $totalPendapatan, $hpp, $ops, $gaji, $lain,
                   $totalPe, $labaKotor, $labaBersih,
                   $b['catatan'] ?? '',
                   $existing,
               ]);
            $laporanId = (int) $existing;
            // Hapus produk lama lalu insert ulang
            $db->prepare('DELETE FROM laporan_produk WHERE laporan_id = ?')->execute([$laporanId]);
        } else {
            // Insert baru
            $db->prepare("INSERT INTO laporan_harian
                (tanggal, jam_buka, jam_tutup,
                 total_pendapatan, hpp,
                 pengeluaran_ops, pengeluaran_gaji, pengeluaran_lain,
                 total_pengeluaran, laba_kotor, laba_bersih, catatan)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([
                   $b['tanggal'], $b['jam_buka'] ?? null, $b['jam_tutup'] ?? null,
                   $totalPendapatan, $hpp, $ops, $gaji, $lain,
                   $totalPe, $labaKotor, $labaBersih,
                   $b['catatan'] ?? '',
               ]);
            $laporanId = (int) $db->lastInsertId();
        }

        // Insert produk
        $stProduk = $db->prepare('INSERT INTO laporan_produk (laporan_id, nama, qty, harga, subtotal) VALUES (?,?,?,?,?)');
        foreach ($b['produk'] as $p) {
            $qty  = (int)   ($p['qty']   ?? 0);
            $hrg  = (float) ($p['harga'] ?? 0);
            $sub  = $qty * $hrg;
            $stProduk->execute([$laporanId, $p['nama'], $qty, $hrg, $sub]);
        }

        $db->commit();
        jsonResponse(['success' => true, 'id' => $laporanId, 'laba_bersih' => $labaBersih]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}

// ── DELETE ────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID tidak valid.'], 400);

    // CASCADE akan hapus laporan_produk juga
    $db->prepare('DELETE FROM laporan_harian WHERE id = ?')->execute([$id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
