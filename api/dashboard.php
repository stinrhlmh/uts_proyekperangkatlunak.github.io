<?php
// =====================================================
// D'one Fried Chicken - Dashboard Summary API
// GET /api/dashboard.php  → semua statistik dashboard
// =====================================================
require_once __DIR__ . '/config.php';
setCorsHeaders();
session_start();

if (!isset($_SESSION['fc_user'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$db    = getDB();
$today = date('Y-m-d');

// ── Laporan hari ini ──────────────────────────────
$st = $db->prepare('SELECT * FROM laporan_harian WHERE tanggal = ? LIMIT 1');
$st->execute([$today]);
$laporanHariIni = $st->fetch() ?: null;

// ── Pengeluaran hari ini ──────────────────────────
$st = $db->prepare('SELECT COUNT(*) AS item_count, COALESCE(SUM(total),0) AS total FROM pengeluaran WHERE tanggal = ?');
$st->execute([$today]);
$peHariIni = $st->fetch();

// ── Barang masuk hari ini ─────────────────────────
$st = $db->prepare('SELECT COUNT(*) AS item_count, COALESCE(SUM(total),0) AS total FROM barang_masuk WHERE tanggal = ?');
$st->execute([$today]);
$bmHariIni = $st->fetch();

// ── Ringkasan 7 hari terakhir ─────────────────────
$st = $db->prepare("
    SELECT COALESCE(SUM(total_pendapatan),0) AS total_pendapatan,
           COALESCE(SUM(laba_bersih),0)      AS total_laba
    FROM laporan_harian
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
");
$st->execute();
$weeklyLaporan = $st->fetch();

$st = $db->prepare("
    SELECT COALESCE(SUM(total),0) AS total
    FROM pengeluaran
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
");
$st->execute();
$weeklyPe = $st->fetch();

$st = $db->prepare("
    SELECT COUNT(*) AS item_count FROM barang_masuk
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
");
$st->execute();
$weeklyBm = $st->fetch();

$st = $db->prepare("
    SELECT COUNT(*) AS item_count FROM barang_keluar
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
");
$st->execute();
$weeklyBk = $st->fetch();

// ── Grafik pendapatan 7 hari ──────────────────────
$st = $db->prepare("
    SELECT tanggal, total_pendapatan, laba_bersih
    FROM laporan_harian
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ORDER BY tanggal ASC
");
$st->execute();
$grafikData = $st->fetchAll();

// ── Tabel terbaru ─────────────────────────────────
$st = $db->prepare('SELECT * FROM barang_masuk ORDER BY tanggal DESC, id DESC LIMIT 5');
$st->execute();
$bmTerbaru = $st->fetchAll();

$st = $db->prepare('SELECT * FROM barang_keluar ORDER BY tanggal DESC, id DESC LIMIT 5');
$st->execute();
$bkTerbaru = $st->fetchAll();

$st = $db->prepare('SELECT * FROM laporan_harian ORDER BY tanggal DESC LIMIT 5');
$st->execute();
$laporanTerbaru = $st->fetchAll();

// ── Format numerik ────────────────────────────────
$floatFields = ['total_pendapatan','laba_bersih','total_pengeluaran',
                'hpp','pengeluaran_ops','pengeluaran_gaji','pengeluaran_lain','laba_kotor'];
foreach ($laporanTerbaru as &$l) {
    foreach ($floatFields as $f) if (isset($l[$f])) $l[$f] = (float) $l[$f];
}
foreach ($bmTerbaru as &$b) {
    $b['jumlah'] = (float) $b['jumlah'];
    $b['harga_satuan'] = (float) $b['harga_satuan'];
    $b['total'] = (float) $b['total'];
}
foreach ($bkTerbaru as &$b) {
    $b['jumlah'] = (int)   $b['jumlah'];
    $b['harga_satuan'] = (float) $b['harga_satuan'];
    $b['total'] = (float) $b['total'];
}
foreach ($grafikData as &$g) {
    $g['total_pendapatan'] = (float) $g['total_pendapatan'];
    $g['laba_bersih']      = (float) $g['laba_bersih'];
}

jsonResponse([
    'success' => true,
    'hari_ini' => [
        'laporan'     => $laporanHariIni
            ? array_map(fn($v, $k) => [$k => in_array($k, $floatFields) ? (float)$v : $v], $laporanHariIni, array_keys($laporanHariIni))
            : null,
        'laporan_raw' => $laporanHariIni ? array_merge($laporanHariIni, array_combine(
            $floatFields,
            array_map(fn($f) => isset($laporanHariIni[$f]) ? (float)$laporanHariIni[$f] : 0, $floatFields)
        )) : null,
        'pengeluaran' => ['item_count' => (int)$peHariIni['item_count'], 'total' => (float)$peHariIni['total']],
        'barang_masuk'=> ['item_count' => (int)$bmHariIni['item_count'], 'total' => (float)$bmHariIni['total']],
    ],
    'weekly' => [
        'pendapatan'  => (float) $weeklyLaporan['total_pendapatan'],
        'laba'        => (float) $weeklyLaporan['total_laba'],
        'pengeluaran' => (float) $weeklyPe['total'],
        'barang_masuk'=> (int)   $weeklyBm['item_count'],
        'barang_keluar'=> (int)  $weeklyBk['item_count'],
    ],
    'grafik'          => $grafikData,
    'bm_terbaru'      => $bmTerbaru,
    'bk_terbaru'      => $bkTerbaru,
    'laporan_terbaru' => $laporanTerbaru,
]);
