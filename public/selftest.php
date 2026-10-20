<?php
// Разместите в public_html и откройте один раз: /selftest.php
// Проверяет готовность хостинга. Удалите после проверки.
header('Content-Type: text/plain; charset=utf-8');
$ok = true;
function check(string $name, bool $pass, string $hint = ''): void {
    global $ok;
    printf("[%s] %s%s\n", $pass ? 'OK ' : 'FAIL', $name, $hint ? " — $hint" : '');
    if (!$pass) $ok = false;
}

check('PHP >= 8.0', PHP_VERSION_ID >= 80000, PHP_VERSION);
check('расширение curl', extension_loaded('curl'));
check('расширение pdo_sqlite', extension_loaded('pdo_sqlite'));
check('расширение json', extension_loaded('json'));
check('расширение hash (md5/sha256/hmac)', extension_loaded('hash'));

$canGoOut = false;
if (extension_loaded('curl')) {
    $ch = curl_init('https://api.openai.com/v1/models');
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 10, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
    $canGoOut = curl_errno($ch) === 0;
    curl_close($ch);
}
check('исходящие HTTPS-запросы (curl)', $canGoOut);

$dir = __DIR__ . '/data';
if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
check('запись в /data', is_dir($dir) && is_writable($dir));

$set = ini_get('max_execution_time');
check('max_execution_time >= 300 (желательно)', (int)$set === 0 || (int)$set >= 300, "сейчас: $set");

check('mod_headers/Apache .htaccess разрешены',
      !isset($_SERVER['SERVER_SOFTWARE']) || str_contains($_SERVER['SERVER_SOFTWARE'], 'Apache')
      || file_exists(__DIR__ . '/.htaccess'));

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? getenv('REDIRECT_HTTP_AUTHORIZATION') ?? '';
check('Authorization header доступен PHP', true,
      $auth !== '' ? 'передаётся' : 'пусто в тесте — проверьте на реальном запросе из клиента');

echo $ok ? "\nВСЁ ГОТОВО — удалите selftest.php\n" : "\nЕСТЬ ПРОБЛЕМЫ — смотрите FAIL выше\n";
