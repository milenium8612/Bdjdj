<?php
declare(strict_types=1);
// Потоковый форвардер запросов (~app/api/common.ts requestOpenai).

require_once __DIR__ . '/bootstrap.php';

function sse_prep(): void {
    header('X-Accel-Buffering: no');       // отключает буферизацию nginx
    if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
    while (ob_get_level() > 0) { @ob_end_clean(); }
}

/**
 * Отправляет запрос на $url и проксирует ответ клиенту "как есть"
 * (SSE-стриминг работает, т.к. echo+flush из callback'а curl).
 * Возвращает [httpStatus, contentType, bytesWritten].
 */
function proxy_stream(string $url, string $method, ?string $body, array $headers): array {
    sse_prep();
    $method = strtoupper($method);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST    => $method,
        CURLOPT_HTTPHEADER       => $headers,
        CURLOPT_TIMEOUT          => PROXY_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT   => 30,
        CURLOPT_HTTP_VERSION     => CURL_HTTP_VERSION_1_1,
        CURLOPT_HEADER           => false,
    ]);
    if ($body !== null && $body !== '' && !in_array($method, ['GET', 'HEAD'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $status = 0; $contentType = 'application/octet-stream'; $written = 0; $headersSent = false;

    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$status, &$contentType) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) { $status = (int)$m[1]; }
        if (stripos($header, 'Content-Type:') === 0) { $contentType = trim(substr($header, 13)); }
        return strlen($header);
    });

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$status, &$contentType, &$written, &$headersSent) {
        if (!$headersSent) {
            $headersSent = true;
            if (!headers_sent()) {
                http_response_code($status > 0 ? $status : 200);
                header('Content-Type: ' . $contentType);
                header('X-Accel-Buffering: no');
            }
        }
        echo $data;
        flush();
        $written += strlen($data);
        return strlen($data);
    });

    curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if (!$headersSent) {
        // пустой ответ / ошибка до получения заголовков
        if (!headers_sent()) {
            http_response_code($err ? 502 : ($status > 0 ? $status : 200));
            header('Content-Type: application/json; charset=utf-8');
        }
        if ($err && $written === 0) {
            echo json_encode(['error' => true, 'msg' => $err], JSON_UNESCAPED_UNICODE);
        }
    }
    return [$status > 0 ? $status : ($err ? 502 : 200), $contentType, $written];
}

/** Буферизованный вариант (для /v1/models с фильтрацией). */
function proxy_buffered(string $url, string $method, ?string $body, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => PROXY_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);
    if ($body !== null && $body !== '' && !in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $resp === '') {
        return [502, 'application/json', json_encode(['error' => true, 'msg' => $err ?: 'empty upstream response'])];
    }
    return [$status > 0 ? $status : 200, $ct ?: 'application/json', $resp];
}

/** Добавляет query string к url (без дублирования '?'). */
function with_query(string $url, string $qs): string {
    if ($qs === '') return $url;
    return $url . (str_contains($url, '?') ? '&' : '?') . $qs;
}
