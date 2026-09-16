<?php
/**
 * webhook.php
 * نقطه ورود اصلی ربات. هم روی هاست (Apache/Nginx + PHP-FPM) کار می‌کنه
 * هم روی سرور با: php -S 0.0.0.0:2000 webhook.php
 *
 * دستورات:
 *   /start                      راهنما
 *   /repo owner/repo            تعیین ریپوی هدف برای این چت
 *   یا هر متن ساده               به عنوان پرامپت به جمینی فرستاده می‌شه
 *   /commit path/در/ریپو [branch]  آخرین پاسخ جمینی رو در اون مسیر کامیت می‌کنه
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/gemini.php';
require_once __DIR__ . '/github.php';
require_once __DIR__ . '/state.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

if (!is_array($update) || !isset($update['message'])) {
    echo json_encode(['ok' => true]); // آپدیت‌های غیرمرتبط (مثل callback_query) رو نادیده می‌گیریم
    exit;
}

$message = $update['message'];
$chatId  = (int)($message['chat']['id'] ?? 0);
$userId  = (string)($message['from']['id'] ?? '');
$text    = trim((string)($message['text'] ?? ''));

if ($chatId === 0) {
    exit;
}

// --- محدودسازی دسترسی: فقط کاربر مجاز ---
if (ALLOWED_TELEGRAM_USER_ID !== '' && $userId !== ALLOWED_TELEGRAM_USER_ID) {
    tg_send_message($chatId, 'اجازه استفاده از این ربات رو نداری.');
    exit;
}

if ($text === '') {
    exit;
}

// --- روتینگ دستورات ---
if (str_starts_with($text, '/start')) {
    tg_send_message($chatId,
        "سلام! این ربات:\n" .
        "1) هر متنی بفرستی با Gemini جواب می‌ده.\n" .
        "2) بعدش با دستور زیر همون خروجی رو روی گیت‌هاب کامیت می‌کنه:\n\n" .
        "/repo owner/repo   → تعیین ریپوی هدف\n" .
        "/commit path/فایل.ext [branch]   → کامیت آخرین پاسخ جمینی\n\n" .
        "مثال:\n/repo mehdi/my-project\nسلام یک تابع فیبوناچی پایتون بنویس\n/commit fib.py"
    );
    exit;
}

if (str_starts_with($text, '/repo')) {
    $parts = preg_split('/\s+/', $text, 2);
    $repo = trim($parts[1] ?? '');
    if ($repo === '' || !str_contains($repo, '/')) {
        tg_send_message($chatId, 'فرمت درست: /repo owner/repo');
        exit;
    }
    state_update($chatId, ['repo' => $repo]);
    tg_send_message($chatId, "ریپوی هدف تنظیم شد: {$repo}");
    exit;
}

if (str_starts_with($text, '/commit')) {
    $parts = preg_split('/\s+/', $text);
    $path = $parts[1] ?? '';
    $branch = $parts[2] ?? 'main';

    if ($path === '') {
        tg_send_message($chatId, 'فرمت درست: /commit path/فایل.ext [branch]');
        exit;
    }

    $state = state_get($chatId);
    $repo = $state['repo'] ?? GITHUB_DEFAULT_REPO;
    $lastOutput = $state['last_output'] ?? '';

    if ($repo === '') {
        tg_send_message($chatId, 'اول ریپو رو تنظیم کن: /repo owner/repo');
        exit;
    }
    if ($lastOutput === '') {
        tg_send_message($chatId, 'هنوز چیزی از جمینی تولید نشده که کامیت بشه. اول یک پیام بفرست.');
        exit;
    }

    $result = github_commit_file($repo, $path, $lastOutput, "Update {$path} via Telegram bot", $branch);

    if ($result['ok']) {
        $msg = "✅ {$result['message']}\nریپو: {$repo}\nمسیر: {$path}\nشاخه: {$branch}";
        if (!empty($result['url'])) {
            $msg .= "\n{$result['url']}";
        }
        tg_send_message($chatId, $msg);
    } else {
        tg_send_message($chatId, "❌ {$result['message']}");
    }
    exit;
}

// --- هر متن دیگه‌ای: پرامپت به جمینی ---
tg_send_message($chatId, 'در حال فکر کردن...');
$reply = gemini_generate($text);
state_update($chatId, ['last_output' => $reply]);
tg_send_message($chatId, $reply);
