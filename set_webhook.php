<?php
/**
 * set_webhook.php
 * یک بار این رو از خط فرمان اجرا کن تا آدرس webhook.php رو به تلگرام معرفی کنی:
 *
 *   php set_webhook.php https://yourdomain.com/webhook.php
 *
 * نکته: تلگرام فقط آدرس HTTPS قبول می‌کنه.
 * برای سرور بدون دامنه/SSL روی پورت 2000، باید یک ریورس‌پراکسی (nginx + certbot)
 * یا سرویسی مثل Cloudflare Tunnel جلوی پورت 2000 بذاری تا HTTPS بگیره.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/http.php';

$url = $argv[1] ?? null;
if (!$url) {
    echo "استفاده: php set_webhook.php https://yourdomain.com/webhook.php\n";
    exit(1);
}

$res = http_request(
    'POST',
    'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/setWebhook',
    ['Content-Type: application/json'],
    json_encode(['url' => $url])
);

echo json_encode($res['body'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
