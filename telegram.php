<?php
/**
 * telegram.php
 * توابع کمکی برای ارسال پیام از طریق Telegram Bot API.
 */

require_once __DIR__ . '/http.php';

function tg_api_url(string $method): string
{
    return 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/' . $method;
}

function tg_send_message(int $chatId, string $text, bool $markdown = false): void
{
    // پیام‌های طولانی رو تلگرام قبول نمی‌کنه (سقف 4096 کاراکتر)، پس چانک می‌کنیم.
    $chunks = mb_str_split($text, 3800);
    if (empty($chunks)) {
        $chunks = [''];
    }
    foreach ($chunks as $chunk) {
        $payload = [
            'chat_id' => $chatId,
            'text'    => $chunk,
        ];
        if ($markdown) {
            $payload['parse_mode'] = 'Markdown';
        }
        http_request('POST', tg_api_url('sendMessage'), ['Content-Type: application/json'], json_encode($payload));
    }
}
