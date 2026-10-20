<?php
declare(strict_types=1);
// TC3-HMAC-SHA256 (~app/utils/tencent.ts)

function tencent_headers(string $payload, string $secretId, string $secretKey): array {
    $endpoint  = 'hunyuan.tencentcloudapi.com';
    $service   = 'hunyuan';
    $action    = 'ChatCompletions';
    $version   = '2023-09-01';
    $timestamp = time();
    $date      = gmdate('Y-m-d', $timestamp);

    $hashedPayload = hash('sha256', $payload);
    $canonicalHeaders = "content-type:application/json\nhost:$endpoint\nx-tc-action:" . strtolower($action) . "\n";
    $signedHeaders = 'content-type;host;x-tc-action';
    $canonicalRequest = implode("\n", ['POST', '/', '', $canonicalHeaders, $signedHeaders, $hashedPayload]);

    $credentialScope = "$date/$service/tc3_request";
    $stringToSign = "TC3-HMAC-SHA256\n$timestamp\n$credentialScope\n" . hash('sha256', $canonicalRequest);

    $kDate    = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
    $kService = hash_hmac('sha256', $service, $kDate, true);
    $kSigning = hash_hmac('sha256', 'tc3_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authorization = "TC3-HMAC-SHA256 Credential=$secretId/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

    return [
        'Authorization: ' . $authorization,
        'Content-Type: application/json',
        'Host: ' . $endpoint,
        'X-TC-Action: ' . $action,
        "X-TC-Timestamp: $timestamp",
        'X-TC-Version: ' . $version,
        'X-TC-Region: ',
    ];
}
