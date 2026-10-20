<?php
declare(strict_types=1);
// (~app/api/auth.ts) ACCESS_CODE_PREFIX = "nk-"; код хранится/проверяется как md5.

require_once __DIR__ . '/bootstrap.php';

const ACCESS_CODE_PREFIX = 'nk-';

/**
 * Разрешает авторизацию и возвращает upstream-значение заголовка Authorization
 * (вида "Bearer <key>" или "Bearer " при пустом системном ключе).
 * При отказе — 401 и exit (формат совпадает с NextChat: {"error":true,"msg":...}).
 */
function auth_bearer(string $systemKeys): string {
    $authHeader = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    $token = preg_replace('/^Bearer\s+/i', '', $authHeader) ?? '';

    $isApiKey = $token !== '' && !str_starts_with($token, ACCESS_CODE_PREFIX);
    $accessCode = str_starts_with($token, ACCESS_CODE_PREFIX) ? substr($token, strlen(ACCESS_CODE_PREFIX)) : '';

    if (need_code() && !code_hash_exists(md5($accessCode)) && !$isApiKey) {
        json_response(401, ['error' => true, 'msg' => $accessCode === '' ? 'empty access code' : 'wrong access code']);
    }
    if (HIDE_USER_API_KEY && $isApiKey) {
        json_response(401, ['error' => true, 'msg' => 'you are not allowed to access with your own api key']);
    }
    if (!$isApiKey) {
        $key = pick_key($systemKeys);
        return 'Bearer ' . $key;
    }
    return 'Bearer ' . $token;
}

/** Извлекает access-код (без префикса) из запроса — для логов. */
function current_access_code(): string {
    $authHeader = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    $token = preg_replace('/^Bearer\s+/i', '', $authHeader) ?? '';
    return str_starts_with($token, ACCESS_CODE_PREFIX) ? substr($token, strlen(ACCESS_CODE_PREFIX)) : '';
}

/** Резолвит "сырое" значение ключа (без Bearer) для нестандартных заголовков (api-key, x-api-key, x-goog-api-key). */
function auth_raw(string $systemKeys, string $clientHeaderName): string {
    $clientValue = trim((string)($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $clientHeaderName))] ?? ''));
    if ($clientValue !== '') return preg_replace('/^Bearer\s+/i', '', $clientValue) ?? '';
    return preg_replace('/^Bearer\s+/i', '', auth_bearer($systemKeys)) ?? '';
}
