<?php
// =====================================================
// D'one Fried Chicken - Pengeluaran API
// GET    /api/pengeluaran.php                        → semua
// GET    /api/pengeluaran.php?tanggal=...            → filter hari
// GET    /api/pengeluaran.php?mode=weekly            → 7 hari terakhir
// POST   /api/pengeluaran.php                        → tambah
// DELETE /api/pengeluaran.php?id=...                 → hapus
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
    $mode = $_GET['mode'] ?? '';

    if ($mode === 'weekly') {
        // Ringkasan per hari selama 7 hari terakhir
        $st = $db->prepare("
            SELECT tanggal,
                   COUNT(*)         AS item_count,
                   SUM(total)       AS total_pengeluaran,
                   GROUP_CONCAT(DISTINCT kategori) AS kategori_list
            FROM pengeluaran
            WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY tanggal
            ORDER BY tanggal ASC
        ");
        $st->execute();
        jsonResponse(['success' => true, 'data' => $st->fetchAll()]);
    }

    if ($mode === 'kategori_weekly') {
        $st = $db->prepare("
            SELECT kategori, SUM(total) AS total
            FROM pengeluaran
            WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY kategori
            ORDER BY total DESC
        ");
        $st->execute();
        jsonResponse(['success' => true, 'data' => $st->fetchAll()]);
    }

    $sql    = 'SELECT * FROM pengeluaran';
    $params = [];

    if (!empty($_GET['tanggal'])) {
        $sql    .= ' WHERE tanggal = ?';
        $params[] = $_GET['tanggal'];
    } elseif (!empty($_GET['kategori'])) {
        $sql    .= ' WHERE kategori = ?';
        $params[] = $_GET['kategori'];
    }
    $sql .= ' ORDER BY tanggal DESC, id DESC';

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    foreach ($rows as &$r) {
        $r['jumlah']  = (float) $r['jumlah'];
        $r['nominal'] = (float) $r['nominal'];
        $r['total']   = (float) $r['total'];
    }
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ── POST ──────────────────────────────────────────
if ($method === 'POST') {
    $b = getBody();

    $required = ['tanggal','kategori','deskripsi','nominal'];
    foreach ($required as $field) {
        if (empty($b[$field]) && $b[$field] !== 0) {
            jsonResponse(['success' => false, 'message' => "Field '$field' wajib diisi."], 400);
        }
    }

    $jumlah  = (float) ($b['jumlah'] ?? 1);
    $nominal = (float) $b['nominal'];
    $total   = $jumlah * $nominal;

    $st = $db->prepare('INSERT INTO pengeluaran
        (tanggal, kategori, deskripsi, jumlah, nominal, total)
        VALUES (?, ?, ?, ?, ?, ?)');
    $st->execute([
        $b['tanggal'], $b['kategori'], $b['deskripsi'],
        $jumlah, $nominal, $total,
    ]);

    jsonResponse(['success' => true, 'id' => (int) $db->lastInsertId()]);
}

// ── DELETE ────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID tidak valid.'], 400);

    $db->prepare('DELETE FROM pengeluaran WHERE id = ?')->execute([$id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
