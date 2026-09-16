<?php
/**
 * config.php
 * تنظیمات ربات از فایل .env خونده می‌شه.
 * روی هاست: .env رو کنار همین فایل‌ها آپلود کن (و با .htaccess بلاکش کن - نمونه پایینه).
 * روی سرور: همون فایل .env کنار پروژه کافیه.
 */

function load_env(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // حذف کوتیشن اطراف مقدار در صورت وجود
        $value = trim($value, "\"'");
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

load_env(__DIR__ . '/.env');

function env(string $key, string $default = ''): string
{
    $val = getenv($key);
    return $val === false ? $default : $val;
}

// --- تنظیمات اصلی ---
define('TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN'));
define('GEMINI_API_KEY', env('GEMINI_API_KEY'));
define('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-2.0-flash'));
define('GITHUB_TOKEN', env('GITHUB_TOKEN'));
define('GITHUB_DEFAULT_REPO', env('GITHUB_DEFAULT_REPO')); // فرم: owner/repo
define('ALLOWED_TELEGRAM_USER_ID', env('ALLOWED_TELEGRAM_USER_ID'));

// فایلی که مکالمه هر کاربر رو موقتاً نگه می‌داره (آخرین متن تولیدشده برای کامیت)
define('STATE_DIR', __DIR__ . '/state');
if (!is_dir(STATE_DIR)) {
    @mkdir(STATE_DIR, 0700, true);
}
