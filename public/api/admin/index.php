<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

// Управление access-кодами (хранятся как md5 — 1:1 с NextChat).
$auth = preg_replace('/^Bearer\s+/i', '', trim($_SERVER['HTTP_AUTHORIZATION'] ?? $_GET['token'] ?? ''));
if (!hash_equals(ADMIN_TOKEN, $auth)) {
    json_response(403, ['error' => true, 'msg' => 'bad admin token']);
}

$action = $_GET['action'] ?? 'list';
$pdo = db();

switch ($action) {
    case 'list':
        json_response(200, $pdo->query('SELECT substr(code_md5,1,8) AS code_md5_prefix, note, active, created_at FROM access_codes ORDER BY created_at DESC')->fetchAll());

    case 'add':
        $code = trim((string)($_POST['code'] ?? $_GET['code'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if (strlen($code) < 4) client_error_('code too short (min 4 chars)');
        $pdo->prepare('INSERT INTO access_codes (code_md5, note, active) VALUES (?,?,1)
                       ON CONFLICT(code_md5) DO UPDATE SET note = excluded.note, active = 1')
            ->execute([md5($code), $note]);
        json_response(200, ['ok' => true, 'show_code_once' => $code]);

    case 'disable': case 'enable':
        $md5 = trim((string)($_POST['md5'] ?? $_GET['md5'] ?? ''));
        $pdo->prepare('UPDATE access_codes SET active = ? WHERE code_md5 LIKE ?')->execute([$action === 'enable' ? 1 : 0, $md5 . '%']);
        json_response(200, ['ok' => true]);

    case 'delete':
        $md5 = trim((string)($_POST['md5'] ?? $_GET['md5'] ?? ''));
        $pdo->prepare('DELETE FROM access_codes WHERE code_md5 LIKE ?')->execute([$md5 . '%']);
        json_response(200, ['ok' => true]);

    case 'stats':
        json_response(200, $pdo->query('SELECT provider, code, model, COUNT(*) AS requests,
            MIN(created_at) AS first_use, MAX(created_at) AS last_use
            FROM usage_log GROUP BY provider, code, model ORDER BY requests DESC')->fetchAll());

    default:
        client_error_('unknown action');
}

function client_error_(string $msg): never {
    json_response(400, ['error' => true, 'msg' => $msg]);
}
