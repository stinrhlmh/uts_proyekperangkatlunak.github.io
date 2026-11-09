<?php
// =====================================================
// D'one Fried Chicken - Barang Keluar API
// GET    /api/barang_keluar.php              → semua data
// GET    /api/barang_keluar.php?tanggal=...  → filter tanggal
// POST   /api/barang_keluar.php              → tambah
// DELETE /api/barang_keluar.php?id=...       → hapus
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
    $sql    = 'SELECT * FROM barang_keluar';
    $params = [];

    if (!empty($_GET['tanggal'])) {
        $sql    .= ' WHERE tanggal = ?';
        $params[] = $_GET['tanggal'];
    }
    $sql .= ' ORDER BY tanggal DESC, id DESC';

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    foreach ($rows as &$r) {
        $r['jumlah']       = (int)   $r['jumlah'];
        $r['harga_satuan'] = (float) $r['harga_satuan'];
        $r['total']        = (float) $r['total'];
    }
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ── POST ──────────────────────────────────────────
if ($method === 'POST') {
    $b = getBody();

    $required = ['tanggal','nama_produk','jumlah','harga_satuan'];
    foreach ($required as $field) {
        if (empty($b[$field]) && $b[$field] !== 0) {
            jsonResponse(['success' => false, 'message' => "Field '$field' wajib diisi."], 400);
        }
    }

    $jumlah = (int)   $b['jumlah'];
    $harga  = (float) $b['harga_satuan'];
    $total  = $jumlah * $harga;

    $st = $db->prepare('INSERT INTO barang_keluar
        (tanggal, nama_produk, jumlah, harga_satuan, total, keterangan)
        VALUES (?, ?, ?, ?, ?, ?)');
    $st->execute([
        $b['tanggal'], $b['nama_produk'],
        $jumlah, $harga, $total,
        $b['keterangan'] ?? '',
    ]);

    jsonResponse(['success' => true, 'id' => (int) $db->lastInsertId()]);
}

// ── DELETE ────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID tidak valid.'], 400);

    $db->prepare('DELETE FROM barang_keluar WHERE id = ?')->execute([$id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
