<?php
declare(strict_types=1);
/* =====================================================================
 * NextChat API — PHP-порт (1:1 к app/api/* репозитория NextChat)
 * Роуты: /api/config, /api/webdav/*, /api/upstash/*, /api/tencent,
 *        /api/artifacts, /api/{provider}/* (16 провайдеров), generic proxy
 * =================================================================== */
$lib = dirname(__DIR__) . '/lib';
require_once $lib . '/bootstrap.php';
require_once $lib . '/auth.php';
require_once $lib . '/proxy.php';
require_once $lib . '/baidu.php';
require_once $lib . '/tencent.php';

/* ---------------- базовые сведения о запросе ---------------- */
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    json_response(200, ['body' => 'OK']);   // как во всех route handlers NextChat
}

$uri  = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri  = '/' . trim($uri, '/');
if ($uri === '//') $uri = '/';
// вырезаем /api
$route = substr($uri, 0, 4) === '/api' ? substr($uri, 4) : $uri;
$route = '/' . trim($route, '/');
$segments = $route === '/' ? [] : explode('/', trim($route, '/'));
$provider = $segments[0] ?? '';
$subpath  = implode('/', array_slice($segments, 1));
$body     = request_body();
$qs       = request_query();

/* ============================ /api/config ============================ */
function handle_config(): never {
    $custom = trim(CUSTOM_MODELS);
    if (DISABLE_GPT4) {
        if ($custom !== '') $custom .= ',';
        // как в server.ts: добавляем "-<model>" для GPT-4 семейства из DEFAULT_MODELS
        $gpt4 = ['gpt-4', 'gpt-4-0613', 'gpt-4-32k', 'gpt-4-32k-0613', 'gpt-4-turbo',
                 'gpt-4-turbo-preview', 'gpt-4-turbo-2024-04-09', 'gpt-4-1106-preview',
                 'gpt-4-vision-preview', 'gpt-4o', 'gpt-4o-2024-05-13', 'gpt-4o-2024-08-06',
                 'gpt-4o-2024-11-20', 'chatgpt-4o-latest', 'gpt-4.1', 'gpt-4.1-2025-04-14',
                 'gpt-4.1-mini', 'gpt-4.1-mini-2025-04-14', 'gpt-4.1-nano', 'gpt-4.1-nano-2025-04-14',
                 'gpt-4.5-preview', 'gpt-4.5-preview-2025-02-27', 'gpt-5', 'gpt-5-chat',
                 'gpt-5-chat-2025-01-01-preview', 'gpt-5-mini', 'gpt-5-nano',
                 'o1', 'o1-2024-12-17', 'o1-preview', 'o1-preview-2024-09-12',
                 'o3', 'o3-mini', 'o3-mini-2025-01-31', 'o4-mini'];
        $custom .= implode(',', array_map(fn ($m) => '-' . $m, $gpt4));
    }
    json_response(200, [
        'needCode'         => need_code(),
        'hideUserApiKey'   => HIDE_USER_API_KEY,
        'disableGPT4'      => DISABLE_GPT4,
        'hideBalanceQuery' => !ENABLE_BALANCE_QUERY,
        'disableFastLink'  => DISABLE_FAST_LINK,
        'customModels'     => $custom,
        'defaultModel'     => DEFAULT_MODEL,
        'visionModels'     => VISION_MODELS,
    ]);
}

/* ====================== провайдеры (app/api/*.ts) ====================== */
const OPENAI_ALLOWED = ['v1/chat/completions', 'v1/audio/speech', 'v1/images/generations',
                        'dashboard/billing/usage', 'dashboard/billing/subscription', 'v1/models'];
const ANTHROPIC_ALLOWED = ['v1/messages', 'v1/complete'];

function openai_base(): string {
    return normalize_base(BASE_URL, 'https://api.openai.com');
}

function handle_openai(string $subpath): never {
    if (!in_array($subpath, OPENAI_ALLOWED, true)) {
        forbid('[OpenAI Route] forbidden path ' . $subpath);
    }
    filter_model_or_die($body = request_body(), 'OpenAI');
    $headers = ['Content-Type: application/json', 'Cache-Control: no-store',
                'Authorization: ' . auth_bearer(OPENAI_API_KEY)];
    if (trim(OPENAI_ORG_ID) !== '') $headers[] = 'OpenAI-Organization: ' . trim(OPENAI_ORG_ID);

    $url = with_query(openai_base() . '/' . $subpath, request_query());

    if ($subpath === 'v1/models') {
        [$status, $ct, $resp] = proxy_buffered($url, 'GET', null, $headers);
        if (DISABLE_GPT4 && $status === 200) {
            $j = json_decode($resp, true);
            if (is_array($j) && isset($j['data']) && is_array($j['data'])) {
                $j['data'] = array_values(array_filter($j['data'], fn ($m) => !is_gpt4_family((string)($m['id'] ?? ''))));
                $resp = json_encode($j);
            }
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo $resp;
        exit;
    }
    log_usage('openai', current_access_code(), model_from($body));
    proxy_stream($url, 'POST', $body, $headers);
    exit;
}

function handle_azure(string $subpath): never {
    filter_model_or_die($body = request_body(), 'Azure');
    $key = trim(AZURE_API_KEY) !== '' ? trim(AZURE_API_KEY) : pick_key(OPENAI_API_KEY);
    if ($key === '') $key = substr(auth_bearer(AZURE_API_KEY), 7);
    $base = trim(AZURE_URL) !== '' ? AZURE_URL : openai_base();
    $base = rtrim(str_contains($base, '/deployments') ? explode('/deployments', $base)[0] : $base, '/');
    if (!str_starts_with($base, 'http')) $base = 'https://' . $base;
    $qs = request_query();
    if ($qs === '' && trim(AZURE_API_VERSION) !== '') $qs = 'api-version=' . urlencode(trim(AZURE_API_VERSION));
    $url = with_query($base . '/' . $subpath, $qs);
    log_usage('azure', current_access_code(), model_from($body));
    proxy_stream($url, 'POST', $body, ['Content-Type: application/json', 'Cache-Control: no-store', 'api-key: ' . $key]);
    exit;
}

function handle_google(string $subpath): never {
    $key = auth_raw(GOOGLE_API_KEY, 'x-goog-api-key');
    $base = normalize_base(GOOGLE_URL, 'https://generativelanguage.googleapis.com');
    $url = with_query($base . '/' . $subpath, request_query()); // сохраняет ?alt=sse
    log_usage('google', current_access_code(), model_from(request_body()));
    proxy_stream($url, request_method(), request_body(),
        ['Content-Type: application/json', 'Cache-Control: no-store', 'x-goog-api-key: ' . $key]);
    exit;
}

function handle_anthropic(string $subpath): never {
    if (!in_array($subpath, ANTHROPIC_ALLOWED, true)) {
        forbid('[Anthropic Route] forbidden path ' . $subpath);
    }
    $body = request_body();
    filter_model_or_die($body, 'Anthropic');
    $key = auth_raw(ANTHROPIC_API_KEY, 'x-api-key');
    $version = trim((string)($_SERVER['HTTP_ANTHROPIC_VERSION'] ?? '')) ?: (trim(ANTHROPIC_API_VERSION) !== '' ? trim(ANTHROPIC_API_VERSION) : '2023-06-01');
    $base = normalize_base(ANTHROPIC_URL, 'https://api.anthropic.com');
    log_usage('anthropic', current_access_code(), model_from($body));
    proxy_stream(with_query($base . '/' . $subpath, request_query()), 'POST', $body, [
        'Content-Type: application/json', 'Cache-Control: no-store',
        'x-api-key: ' . $key, 'anthropic-version: ' . $version,
        'anthropic-dangerous-direct-browser-access: true',
    ]);
    exit;
}

function handle_baidu(string $subpath): never {
    if (trim(BAIDU_API_KEY) === '' || trim(BAIDU_SECRET_KEY) === '') {
        json_response(500, ['error' => true, 'msg' => 'BAIDU_API_KEY/BAIDU_SECRET_KEY not configured']);
    }
    $body = request_body();
    filter_model_or_die($body, 'Baidu');
    $base = normalize_base(BAIDU_URL, 'https://aip.baidubce.com');
    $url = with_query($base . '/' . $subpath, 'access_token=' . urlencode(baidu_access_token()));
    log_usage('baidu', current_access_code(), model_from($body));
    proxy_stream($url, 'POST', $body, ['Content-Type: application/json', 'Cache-Control: no-store']);
    exit;
}

/** Общий Bearer-прокси для OpenAI-совместимых провайдеров (~по аналогии с app/api/*.ts). */
function handle_generic_bearer(string $provider, string $subpath, string $baseDefault, string $keys, string $serviceName): never {
    $body = request_body();
    filter_model_or_die($body, $serviceName);
    $base = normalize_base(provider_base_const($provider), $baseDefault);
    $headers = ['Content-Type: application/json', 'Cache-Control: no-store',
                'Authorization: ' . auth_bearer($keys)];
    if ($provider === 'alibaba') {
        $headers[] = 'X-DashScope-SSE: ' . ($_SERVER['HTTP_X_DASHSCOPE_SSE'] ?? 'disable');
    }
    log_usage($provider, current_access_code(), model_from($body));
    proxy_stream(with_query($base . '/' . $subpath, request_query()), 'POST', $body, $headers);
    exit;
}

function provider_base_const(string $provider): string {
    return match ($provider) {
        'bytedance'  => BYTEDANCE_URL,
        'alibaba'    => ALIBABA_URL,
        'moonshot'   => MOONSHOT_URL,
        'iflytek'    => IFLYTEK_URL,
        'deepseek'   => DEEPSEEK_URL,
        'xai'        => XAI_URL,
        'chatglm'    => CHATGLM_URL,
        'siliconflow'=> SILICONFLOW_URL,
        '302ai'      => AI302_URL,
        default      => '',
    };
}
function provider_keys_const(string $provider): string {
    return match ($provider) {
        'bytedance'   => BYTEDANCE_API_KEY,
        'alibaba'     => ALIBABA_API_KEY,
        'moonshot'    => MOONSHOT_API_KEY,
        'iflytek'     => trim(IFLYTEK_API_KEY) !== '' ? IFLYTEK_API_KEY . ':' . IFLYTEK_API_SECRET : '',
        'deepseek'    => DEEPSEEK_API_KEY,
        'xai'         => XAI_API_KEY,
        'chatglm'     => CHATGLM_API_KEY,
        'siliconflow' => SILICONFLOW_API_KEY,
        '302ai'       => AI302_API_KEY,
        default       => '',
    };
}

function handle_stability(string $subpath): never {
    $auth = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    $key = preg_replace('/^Bearer\s+/i', '', $auth) ?: pick_key(STABILITY_API_KEY);
    $base = normalize_base(STABILITY_URL, 'https://api.stability.ai');
    proxy_stream(with_query($base . '/' . $subpath, request_query()), request_method(), request_body(),
        ['Content-Type: ' . ($_SERVER['HTTP_CONTENT_TYPE'] ?? 'multipart/form-data'),
         'Accept: ' . ($_SERVER['HTTP_ACCEPT'] ?? 'application/json'),
         'Authorization: Bearer ' . $key]);
    exit;
}

function handle_tencent(): never {
    $auth = auth_bearer(TENCENT_SECRET_ID); // валидирует access-код/пользовательский ключ
    if (trim(TENCENT_SECRET_ID) === '' || trim(TENCENT_SECRET_KEY) === '') {
        json_response(500, ['error' => true, 'msg' => 'TENCENT_SECRET_ID/TENCENT_SECRET_KEY not configured']);
    }
    $base = normalize_base(TENCENT_URL, 'https://hunyuan.tencentcloudapi.com');
    $body = request_body();
    filter_model_or_die($body, 'Tencent');
    log_usage('tencent', current_access_code(), model_from($body));
    proxy_stream($base, 'POST', $body, tencent_headers($body, trim(TENCENT_SECRET_ID), trim(TENCENT_SECRET_KEY)));
    exit;
}

/** generic proxy (~app/api/proxy.ts): x-base-url + любой подпуть. */
function handle_proxy(string $subpath): never {
    $base = trim((string)($_SERVER['HTTP_X_BASE_URL'] ?? ''));
    if ($base === '') forbid('[Proxy Route] x-base-url header is missing');
    $headers = ['Content-Type: ' . ($_SERVER['HTTP_CONTENT_TYPE'] ?? 'application/json')];
    if (str_contains($base, 'api.openai.com') && trim(OPENAI_API_KEY) !== '') {
        $headers[] = 'Authorization: Bearer ' . pick_key(OPENAI_API_KEY);
    }
    $base = rtrim($base, '/');
    if (!str_starts_with($base, 'http')) $base = 'https://' . $base;
    proxy_stream(with_query($base . '/' . $subpath, request_query()), request_method(), request_body(), $headers);
    exit;
}

/* =================== WebDAV (~app/api/webdav/[...path]) =================== */
function handle_webdav(array $segments): never {
    $endpoint = (string)($_GET['endpoint'] ?? '');
    $proxyMethod = strtoupper((string)($_GET['proxy_method'] ?? $_SERVER['REQUEST_METHOD']));
    $allowed = ['https://dav.jianguoyun.com/dav/', 'https://dav.dropdav.com/', 'https://dav.box.com/dav',
                'https://nanao.teracloud.jp/dav/', 'https://bora.teracloud.jp/dav/', 'https://webdav.4shared.com/',
                'https://dav.idrivesync.com', 'https://webdav.yandex.com', 'https://app.koofr.net/dav/Koofr'];
    foreach (array_filter(array_map('trim', explode(',', WHITE_WEBDAV_ENDPOINTS))) as $e) $allowed[] = $e;

    $ep = parse_url($endpoint);
    $ok = $ep && isset($ep['host']);
    if ($ok) {
        $ok = false;
        foreach ($allowed as $a) {
            $ap = parse_url($a);
            if ($ap && ($ap['host'] ?? '') === $ep['host']
                && str_starts_with($ep['path'] ?? '/', $ap['path'] ?? '/')) { $ok = true; break; }
        }
    }
    if (!$ok) json_response(400, ['error' => true, 'msg' => 'Invalid endpoint']);

    $folder = 'chatgpt-next-web';
    $fileName = $folder . '/backup.json';
    $endpointPath = implode('/', array_slice($segments, 1));
    if (!str_ends_with($endpoint, '/')) $endpoint .= '/';
    $target = $endpoint . $endpointPath;

    if (!in_array($proxyMethod, ['MKCOL', 'GET', 'PUT'], true)) {
        forbid('you are not allowed to request ' . $target);
    }
    if ($proxyMethod === 'MKCOL' && !str_ends_with($target, $folder)) forbid('you are not allowed to request ' . $target);
    if ($proxyMethod === 'GET'  && !str_ends_with($target, $fileName)) forbid('you are not allowed to request ' . $target);
    if ($proxyMethod === 'PUT'  && !str_ends_with($target, $fileName)) forbid('you are not allowed to request ' . $target);

    proxy_stream($target, $proxyMethod, in_array($proxyMethod, ['GET', 'HEAD'], true) ? null : request_body(),
        ['Authorization: ' . (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '')]);
    exit;
}

/* =================== Upstash (~app/api/upstash/[action]/[...key]) =================== */
function handle_upstash(array $segments): never {
    $endpoint = (string)($_GET['endpoint'] ?? '');
    $action = $segments[1] ?? '';
    $keyParts = array_slice($segments, 2);
    $ep = parse_url($endpoint);
    if (!$ep || !str_ends_with($ep['host'] ?? '', '.upstash.io')) {
        forbid('you are not allowed to request ' . implode('/', $keyParts));
    }
    if ($action !== 'get' && $action !== 'set') {
        forbid('you are not allowed to request ' . $action);
    }
    $target = rtrim($endpoint, '/') . '/' . $action . '/' . implode('/', $keyParts);
    proxy_stream(with_query($target, request_query()), strtoupper((string)$_SERVER['REQUEST_METHOD']),
        request_body(), ['Authorization: ' . (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '')]);
    exit;
}

/* =================== Artifacts (~app/api/artifacts) =================== */
function handle_artifacts(): never {
    if (trim(CLOUDFLARE_ACCOUNT_ID) === '' || trim(CLOUDFLARE_KV_NAMESPACE_ID) === '' || trim(CLOUDFLARE_KV_API_KEY) === '') {
        json_response(400, ['error' => true, 'msg' => 'Cloudflare KV is not configured on server']);
    }
    $store = 'https://api.cloudflare.com/client/v4/accounts/' . CLOUDFLARE_ACCOUNT_ID
           . '/storage/kv/namespaces/' . CLOUDFLARE_KV_NAMESPACE_ID;
    if (strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST') {
        $body = request_body();
        $hashed = md5($body);
        $entry = ['key' => $hashed, 'value' => $body];
        $ttl = (int)CLOUDFLARE_KV_TTL;
        if ($ttl > 60) $entry['expiration_ttl'] = $ttl;
        [$status] = proxy_stream($store . '/bulk', 'PUT', json_encode([$entry]),
            ['Authorization: Bearer ' . CLOUDFLARE_KV_API_KEY, 'Content-Type: application/json']);
        if ($status >= 200 && $status < 300) {
            json_response(200, ['code' => 0, 'id' => $hashed, 'result' => null]);
        }
        json_response(400, ['error' => true, 'msg' => 'Save data error']);
    }
    if (strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'GET') {
        $id = (string)($_GET['id'] ?? '');
        proxy_stream($store . '/values/' . urlencode($id), 'GET', null,
            ['Authorization: Bearer ' . CLOUDFLARE_KV_API_KEY]);
        exit;
    }
    json_response(400, ['error' => true, 'msg' => 'Invalid request']);
}

/* ======================= мелкие утилиты ======================= */
function request_method(): string { return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')); }

function model_from(string $body): string {
    $j = json_decode($body, true);
    return is_array($j) && isset($j['model']) ? (string)$j['model'] : '';
}

/* ======================= роутинг ======================= */
switch ($provider) {
    case '': case 'config':
        if (($segments[1] ?? '') === '' || $provider === 'config') handle_config();
        break;
    case 'webdav':  handle_webdav($segments);
    case 'upstash': handle_upstash($segments);
    case 'tencent': handle_tencent();
    case 'artifacts': handle_artifacts();
    case 'openai':    handle_openai($subpath);
    case 'azure':     handle_azure($subpath);
    case 'google':    handle_google($subpath);
    case 'anthropic': handle_anthropic($subpath);
    case 'baidu':     handle_baidu($subpath);
    case 'stability': handle_stability($subpath);
    case 'bytedance': case 'alibaba': case 'moonshot': case 'iflytek':
    case 'deepseek': case 'xai': case 'chatglm': case 'siliconflow': case '302ai':
        $defaults = ['bytedance' => 'https://ark.cn-beijing.volces.com',
                     'alibaba' => 'https://dashscope.aliyuncs.com/api/',
                     'moonshot' => 'https://api.moonshot.ai',
                     'iflytek' => 'https://spark-api-open.xf-yun.com',
                     'deepseek' => 'https://api.deepseek.com',
                     'xai' => 'https://api.x.ai',
                     'chatglm' => 'https://open.bigmodel.cn',
                     'siliconflow' => 'https://api.siliconflow.cn',
                     '302ai' => 'https://api.302.ai'];
        $names = ['bytedance' => 'ByteDance', 'alibaba' => 'Alibaba', 'moonshot' => 'Moonshot',
                  'iflytek' => 'Iflytek', 'deepseek' => 'DeepSeek', 'xai' => 'XAI',
                  'chatglm' => 'ChatGLM', 'siliconflow' => 'SiliconFlow', '302ai' => '302.AI'];
        handle_generic_bearer($provider, $subpath, $defaults[$provider], provider_keys_const($provider), $names[$provider]);
    default:
        handle_proxy($subpath);
}
