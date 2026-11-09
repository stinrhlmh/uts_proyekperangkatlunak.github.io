<?php
// =====================================================
// D'one Fried Chicken - Diagnostik API (HAPUS setelah selesai setup)
// =====================================================
require_once __DIR__ . '/config.php';
setCorsHeaders();

$action = $_GET['action'] ?? 'ping';

// ── Ping ─────────────────────────────────────────
if ($action === 'ping') {
    jsonResponse([
        'success'     => true,
        'php_version' => PHP_VERSION,
        'time'        => date('Y-m-d H:i:s'),
    ]);
}

// ── Test koneksi DB ───────────────────────────────
if ($action === 'db') {
    try {
        $db = getDB();
        $db->query('SELECT 1');
        jsonResponse(['success' => true, 'db_name' => DB_NAME]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ── Cek tabel ────────────────────────────────────
if ($action === 'tables') {
    try {
        $db   = getDB();
        $st   = $db->query("SHOW TABLES");
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse(['success' => true, 'tables' => $rows]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage(), 'tables' => []]);
    }
}

// ── Cek users ─────────────────────────────────────
if ($action === 'users') {
    try {
        $db    = getDB();
        $st    = $db->query("SELECT id, username, role, LEFT(password,7) as pw_prefix FROM users");
        $users = $st->fetchAll();
        foreach ($users as &$u) {
            // Cek apakah password adalah bcrypt hash ($2y$ prefix)
            $u['pw_ok'] = str_starts_with($u['pw_prefix'], '$2y$10');
            unset($u['pw_prefix']);
        }
        jsonResponse(['success' => true, 'users' => $users]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage(), 'users' => []]);
    }
}

// ── Seed password ─────────────────────────────────
if ($action === 'seed_users') {
    try {
        $db = getDB();
        $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'")
           ->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
        $db->prepare("UPDATE users SET password = ? WHERE username = 'kasir'")
           ->execute([password_hash('kasir123', PASSWORD_DEFAULT)]);
        $rows = $db->query("SELECT COUNT(*) FROM users WHERE username IN ('admin','kasir')")->fetchColumn();
        if ((int)$rows === 0) {
            // Insert jika belum ada
            $db->prepare("INSERT INTO users (username, password, nama, role) VALUES (?,?,?,?)")
               ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'Admin']);
            $db->prepare("INSERT INTO users (username, password, nama, role) VALUES (?,?,?,?)")
               ->execute(['kasir', password_hash('kasir123', PASSWORD_DEFAULT), 'Kasir', 'Kasir']);
        }
        jsonResponse(['success' => true, 'message' => 'Password berhasil di-set. Silakan coba login!']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

jsonResponse(['success' => false, 'message' => 'Action tidak dikenal.'], 400);
