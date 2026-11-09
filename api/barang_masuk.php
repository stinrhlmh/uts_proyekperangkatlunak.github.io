<?php
// =====================================================
// D'one Fried Chicken - Barang Masuk API
// GET    /api/barang_masuk.php              → semua data
// GET    /api/barang_masuk.php?tanggal=...  → filter tanggal
// POST   /api/barang_masuk.php              → tambah
// DELETE /api/barang_masuk.php?id=...       → hapus
// =====================================================
require_once __DIR__ . '/config.php';
setCorsHeaders();
session_start();

if (!isset($_SESSION['fc_user'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ── GET ───────────────────────────────────────────
if ($method === 'GET') {
    $sql    = 'SELECT * FROM barang_masuk';
    $params = [];

    if (!empty($_GET['tanggal'])) {
        $sql    .= ' WHERE tanggal = ?';
        $params[] = $_GET['tanggal'];
    }
    $sql .= ' ORDER BY tanggal DESC, id DESC';

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    // Format angka agar JS tidak salah parse
    foreach ($rows as &$r) {
        $r['jumlah']       = (float) $r['jumlah'];
        $r['harga_satuan'] = (float) $r['harga_satuan'];
        $r['total']        = (float) $r['total'];
    }
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ── POST ──────────────────────────────────────────
if ($method === 'POST') {
    $b = getBody();

    $required = ['tanggal','nama_barang','kategori','jumlah','satuan','harga_satuan'];
    foreach ($required as $field) {
        if (empty($b[$field]) && $b[$field] !== 0) {
            jsonResponse(['success' => false, 'message' => "Field '$field' wajib diisi."], 400);
        }
    }

    $jumlah  = (float) $b['jumlah'];
    $harga   = (float) $b['harga_satuan'];
    $total   = $jumlah * $harga;

    $st = $db->prepare('INSERT INTO barang_masuk
        (tanggal, nama_barang, kategori, jumlah, satuan, harga_satuan, total, supplier, keterangan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([
        $b['tanggal'], $b['nama_barang'], $b['kategori'],
        $jumlah, $b['satuan'], $harga, $total,
        $b['supplier'] ?? '', $b['keterangan'] ?? '',
    ]);

    jsonResponse(['success' => true, 'id' => (int) $db->lastInsertId()]);
}

// ── DELETE ────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID tidak valid.'], 400);

    $db->prepare('DELETE FROM barang_masuk WHERE id = ?')->execute([$id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
