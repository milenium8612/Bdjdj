<?php
declare(strict_types=1);
// (~app/utils/baidu.ts) access_token кэшируется в SQLite.

require_once __DIR__ . '/bootstrap.php';

function baidu_access_token(): string {
    $row = db()->query('SELECT token, expires_at FROM baidu_token WHERE id = 1')->fetch();
    if ($row && (int)$row['expires_at'] > time() + 3600) return (string)$row['token'];

    $url = 'https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials'
         . '&client_id=' . urlencode(BAIDU_API_KEY) . '&client_secret=' . urlencode(BAIDU_SECRET_KEY);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $j = json_decode((string)$resp, true);
    if (!is_array($j) || empty($j['access_token'])) {
        json_response(500, ['error' => true, 'msg' => 'Failed to get baidu access token']);
    }
    $expiresIn = (int)($j['expires_in'] ?? 0);
    db()->prepare('INSERT INTO baidu_token (id, token, expires_at) VALUES (1, ?, ?)
                   ON CONFLICT(id) DO UPDATE SET token = excluded.token, expires_at = excluded.expires_at')
        ->execute([(string)$j['access_token'], time() + max(0, $expiresIn)]);
    return (string)$j['access_token'];
}
