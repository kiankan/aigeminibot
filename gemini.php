<?php
/**
 * gemini.php
 * ارتباط با Gemini API رسمی گوگل با استفاده از API Key.
 * https://ai.google.dev/api/generate-content
 */

require_once __DIR__ . '/http.php';

/**
 * پیام رو به جمینی می‌فرسته و متن پاسخ رو برمی‌گردونه.
 */
function gemini_generate(string $prompt): string
{
    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
        GEMINI_MODEL,
        urlencode(GEMINI_API_KEY)
    );

    $payload = json_encode([
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ],
    ]);

    $res = http_request('POST', $url, ['Content-Type: application/json'], $payload, 120);

    if (!$res['ok']) {
        $msg = is_array($res['body']) ? json_encode($res['body']) : (string)$res['body'];
        return "خطا در تماس با Gemini (HTTP {$res['status']}): {$msg}";
    }

    $body = $res['body'];
    $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($text === null) {
        return 'جمینی پاسخی برنگردوند. پاسخ خام: ' . json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    return $text;
}
