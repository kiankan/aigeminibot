<?php
/**
 * http.php
 * یک تابع کوچیک برای درخواست‌های HTTP با cURL - بدون نیاز به هیچ کتابخونه‌ای.
 */

function http_request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 60): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'status' => 0, 'error' => $error, 'body' => null];
    }

    $decoded = json_decode($response, true);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'error' => null, 'body' => $decoded ?? $response];
}
