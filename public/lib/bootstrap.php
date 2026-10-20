<?php
declare(strict_types=1);
date_default_timezone_set('UTC');

/* =====================================================================
 * Конфиг. Имена констант = имена env-переменных NextChat (app/config/server.ts)
 * Заполните ключи тех провайдеров, которыми будете пользоваться.
 * =================================================================== */

// --- OpenAI (также fallback для azure и generic-proxy на api.openai.com) ---
const OPENAI_API_KEY = '';            // можно несколько через запятую (выбор случайный, как в NextChat)
const BASE_URL = '';                  // пусто = https://api.openai.com
const OPENAI_ORG_ID = '';
const CODE = '';                      // резервные коды доступа через запятую (основное хранилище — SQLite)

// --- поведение (DANGER_CONFIG) ---
const HIDE_USER_API_KEY = false;      // запретить пользователям свои ключи
const DISABLE_GPT4 = false;
const ENABLE_BALANCE_QUERY = false;
const DISABLE_FAST_LINK = false;
const CUSTOM_MODELS = '';             // например: "-all,+gpt-4o@OpenAI,deepseek-chat@DeepSeek"
const DEFAULT_MODEL = '';
const VISION_MODELS = '';

// --- Azure ---
const AZURE_URL = '';                 // https://{resource}.openai.azure.com/openai/deployments/{deploy}
const AZURE_API_KEY = '';
const AZURE_API_VERSION = '';

// --- Google Gemini ---
const GOOGLE_API_KEY = '';
const GOOGLE_URL = '';                // пусто = https://generativelanguage.googleapis.com/

// --- Anthropic ---
const ANTHROPIC_API_KEY = '';
const ANTHROPIC_URL = '';
const ANTHROPIC_API_VERSION = '';     // пусто = 2023-06-01

// --- Baidu Ernie ---
const BAIDU_URL = '';
const BAIDU_API_KEY = '';
const BAIDU_SECRET_KEY = '';

// --- ByteDance Doubao ---
const BYTEDANCE_URL = '';
const BYTEDANCE_API_KEY = '';

// --- Alibaba Qwen ---
const ALIBABA_URL = '';
const ALIBABA_API_KEY = '';

// --- Tencent Hunyuan ---
const TENCENT_URL = '';
const TENCENT_SECRET_ID = '';
const TENCENT_SECRET_KEY = '';

// --- Moonshot ---
const MOONSHOT_URL = '';
const MOONSHOT_API_KEY = '';

// --- iFlytek Spark ---
const IFLYTEK_URL = '';
const IFLYTEK_API_KEY = '';
const IFLYTEK_API_SECRET = '';

// --- DeepSeek ---
const DEEPSEEK_URL = '';
const DEEPSEEK_API_KEY = '';

// --- xAI ---
const XAI_URL = '';
const XAI_API_KEY = '';

// --- ChatGLM ---
const CHATGLM_URL = '';
const CHATGLM_API_KEY = '';

// --- SiliconFlow ---
const SILICONFLOW_URL = '';
const SILICONFLOW_API_KEY = '';

// --- 302.AI ---
const AI302_URL = '';
const AI302_API_KEY = '';

// --- Stability ---
const STABILITY_URL = '';
const STABILITY_API_KEY = '';

// --- WebDAV allowlist (дополнение к встроенному) ---
const WHITE_WEBDAV_ENDPOINTS = '';

// --- Cloudflare KV (для /api/artifacts) ---
const CLOUDFLARE_ACCOUNT_ID = '';
const CLOUDFLARE_KV_NAMESPACE_ID = '';
const CLOUDFLARE_KV_API_KEY = '';
const CLOUDFLARE_KV_TTL = '';

// --- админка access-кодов ---
const ADMIN_TOKEN = 'change-me-long-random-string';
const LOG_REQUESTS = true;
const PROXY_TIMEOUT = 600;            // сек, как в NextChat (10 мин)

/* =================== SQLite =================== */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dir = __DIR__ . '/../data';
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        $pdo = new PDO('sqlite:' . $dir . '/nextchat.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("CREATE TABLE IF NOT EXISTS access_codes (
            code_md5 TEXT PRIMARY KEY, note TEXT DEFAULT '', active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS baidu_token (
            id INTEGER PRIMARY KEY CHECK (id = 1), token TEXT,
            expires_at INTEGER DEFAULT 0)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS usage_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT, provider TEXT, code TEXT, model TEXT,
            ip TEXT, created_at TEXT DEFAULT (datetime('now')))");
    }
    return $pdo;
}

function code_hash_exists(string $md5): bool {
    if ($md5 === '' || $md5 === md5('')) return false;
    $st = db()->prepare('SELECT 1 FROM access_codes WHERE code_md5 = ? AND active = 1');
    $st->execute([$md5]);
    return (bool)$st->fetchColumn();
}

function need_code(): bool {
    if (trim(CODE) !== '') return true;
    return (int)db()->query('SELECT COUNT(*) FROM access_codes WHERE active = 1')->fetchColumn() > 0;
}

function log_usage(string $provider, string $code, string $model): void {
    if (!LOG_REQUESTS) return;
    try {
        $st = db()->prepare('INSERT INTO usage_log (provider, code, model, ip) VALUES (?,?,?,?)');
        $st->execute([$provider, $code, $model, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $e) {}
}

/* =================== helpers =================== */
function json_response(int $status, array $data): never {
    http_response_code($status);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function forbid(string $msg, int $status = 403): never {
    json_response($status, ['error' => true, 'msg' => $msg]);
}

function pick_key(string $keys): string {
    $keys = trim($keys);
    if ($keys === '') return '';
    $arr = array_map('trim', explode(',', $keys));
    $arr = array_values(array_filter($arr));
    if (!$arr) return '';
    return $arr[array_rand($arr)];   // как getApiKey() в NextChat
}

function normalize_base(string $base, string $default): string {
    $base = trim($base) !== '' ? trim($base) : $default;
    if (!str_starts_with($base, 'http')) $base = 'https://' . $base;
    return rtrim($base, '/');
}

function request_body(): string {
    return (string)file_get_contents('php://input');
}

function request_query(): string {
    return (string)($_SERVER['QUERY_STRING'] ?? '');
}

function is_gpt4_family(string $name): bool {
    return str_starts_with($name, 'gpt-4') || str_starts_with($name, 'chatgpt-4o')
        || str_starts_with($name, 'o1') || str_starts_with($name, 'o3');
}

/* Фильтр CUSTOM_MODELS (~app/utils/model.ts). providerNames: [ServiceProvider, ...] */
function model_unavailable(string $model, array $providerNames): bool {
    $cm = trim(CUSTOM_MODELS);
    if (DISABLE_GPT4 && is_gpt4_family($model)) return true;
    if ($cm === '') return false;
    $blocked = false; $hasAllow = false; $allowed = false;
    foreach (explode(',', $cm) as $raw) {
        $e = trim($raw); if ($e === '') continue;
        $neg = str_starts_with($e, '-'); if ($neg) $e = substr($e, 1);
        $name = $e; $provider = null;
        if (str_contains($e, '@')) { [$name, $provider] = explode('@', $e, 2); }
        $name = explode('=', $name)[0]; // отбрасываем "=displayName"
        if ($provider !== null && !in_array($provider, $providerNames, true)) continue;
        if ($name === 'all' || strcasecmp($name, $model) === 0) {
            if ($neg) { $blocked = true; } else { $hasAllow = true; $allowed = true; }
        }
    }
    if ($blocked) return true;
    if ($hasAllow && !$allowed) return true;
    return false;
}

function filter_model_or_die(string $body, string $providerName): void {
    $j = json_decode($body, true);
    if (is_array($j) && !empty($j['model']) && model_unavailable((string)$j['model'], [$providerName, (string)$j['model']])) {
        forbid('you are not allowed to use ' . $j['model'] . ' model');
    }
}
