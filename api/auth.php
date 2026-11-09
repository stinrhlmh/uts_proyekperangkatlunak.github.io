<?php
// =====================================================
// D'one Fried Chicken - Auth API
// POST /api/auth.php?action=login   { username, password }
// POST /api/auth.php?action=logout
// GET  /api/auth.php?action=check
// =====================================================
require_once __DIR__ . '/config.php';
setCorsHeaders();
session_start();

$action = $_GET['action'] ?? '';

// ── Login ──────────────────────────────────────────
if ($action === 'login') {
    $body = getBody();
    $username = trim(strtolower($body['username'] ?? ''));
    $password = $body['password'] ?? '';

    if (!$username || !$password) {
        jsonResponse(['success' => false, 'message' => 'Username dan password wajib diisi.'], 400);
    }

    $db  = getDB();
    $st  = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Username atau password salah.'], 401);
    }

    $_SESSION['fc_user'] = [
        'id'       => $user['id'],
        'username' => $user['username'],
        'nama'     => $user['nama'],
        'role'     => $user['role'],
    ];

    jsonResponse(['success' => true, 'user' => $_SESSION['fc_user']]);
}

// ── Logout ─────────────────────────────────────────
if ($action === 'logout') {
    session_destroy();
    jsonResponse(['success' => true]);
}

// ── Check session ──────────────────────────────────
if ($action === 'check') {
    if (isset($_SESSION['fc_user'])) {
        jsonResponse(['success' => true, 'user' => $_SESSION['fc_user']]);
    }
    jsonResponse(['success' => false, 'message' => 'Belum login.'], 401);
}

// ── Seed password (jalankan sekali untuk set password benar) ─
if ($action === 'seed_users') {
    $db = getDB();
    $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'")
       ->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
    $db->prepare("UPDATE users SET password = ? WHERE username = 'kasir'")
       ->execute([password_hash('kasir123', PASSWORD_DEFAULT)]);
    jsonResponse(['success' => true, 'message' => 'Password users berhasil di-seed.']);
}

jsonResponse(['success' => false, 'message' => 'Action tidak dikenal.'], 400);
