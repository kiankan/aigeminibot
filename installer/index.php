<?php
/**
 * installer/index.php
 * نصاب گرافیکی برای هاست اشتراکی.
 * این فایل رو داخل مرورگر باز کن (مثلاً yourdomain.com/aigeminibot/installer/)،
 * فرم رو پر کن، و به صورت خودکار:
 *   1) فایل .env رو یک پوشه بالاتر (ریشه پروژه) می‌سازه
 *   2) webhook تلگرام رو به آدرس webhook.php ثبت می‌کنه
 *
 * ⚠️ بعد از اتمام نصب، حتماً همین پوشه installer رو از روی هاست پاک کن،
 * چون هرکسی که آدرسش رو پیدا کنه می‌تونه دوباره تنظیمات رو عوض کنه.
 */

$rootDir = dirname(__DIR__);
$envPath = $rootDir . '/.env';
$message = '';
$success = false;

function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // مسیر پوشه installer رو حذف می‌کنیم تا به ریشه پروژه برسیم
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $projectDir = preg_replace('#/installer$#', '', $scriptDir);
    return $scheme . '://' . $host . $projectDir;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telegramToken = trim($_POST['telegram_token'] ?? '');
    $geminiKey     = trim($_POST['gemini_key'] ?? '');
    $geminiModel   = trim($_POST['gemini_model'] ?? '') ?: 'gemini-2.0-flash';
    $githubToken   = trim($_POST['github_token'] ?? '');
    $githubRepo    = trim($_POST['github_repo'] ?? '');
    $allowedUser   = trim($_POST['allowed_user'] ?? '');

    if ($telegramToken === '' || $geminiKey === '' || $githubToken === '' || $allowedUser === '') {
        $message = 'لطفاً همه فیلدهای الزامی (توکن تلگرام، Gemini، GitHub، آیدی تلگرام) رو پر کن.';
    } else {
        $envContent = <<<ENV
TELEGRAM_BOT_TOKEN={$telegramToken}
GEMINI_API_KEY={$geminiKey}
GEMINI_MODEL={$geminiModel}
GITHUB_TOKEN={$githubToken}
GITHUB_DEFAULT_REPO={$githubRepo}
ALLOWED_TELEGRAM_USER_ID={$allowedUser}

ENV;

        if (file_put_contents($envPath, $envContent) === false) {
            $message = 'نتونستم فایل .env رو بنویسم. مطمئن شو پوشه ریشه پروژه قابل نوشتنه (chmod 755 یا 775).';
        } else {
            // ثبت webhook در تلگرام
            $webhookUrl = base_url() . '/webhook.php';
            $apiUrl = "https://api.telegram.org/bot{$telegramToken}/setWebhook";
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['url' => $webhookUrl]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode((string)$response, true);

            if (!empty($result['ok'])) {
                $success = true;
                $message = "نصب کامل شد! Webhook روی این آدرس ثبت شد:\n{$webhookUrl}";
            } else {
                $errDesc = $result['description'] ?? 'پاسخ نامشخص از تلگرام';
                $message = ".env ساخته شد اما ثبت webhook با خطا مواجه شد: {$errDesc}\n" .
                           "بررسی کن که آدرس زیر از بیرون (با HTTPS معتبر) در دسترس باشه:\n{$webhookUrl}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>نصب ربات Gemini ↔ GitHub</title>
<style>
    body { font-family: Tahoma, sans-serif; background: #0f172a; color: #e2e8f0; margin:0; padding: 30px; }
    .box { max-width: 520px; margin: 0 auto; background: #1e293b; padding: 24px; border-radius: 12px; }
    h1 { font-size: 20px; margin-bottom: 4px; }
    p.sub { color: #94a3b8; font-size: 13px; margin-top: 0; }
    label { display:block; margin-top: 14px; font-size: 13px; color:#cbd5e1; }
    input { width: 100%; box-sizing: border-box; padding: 10px; margin-top: 4px; border-radius: 8px; border: 1px solid #334155; background:#0f172a; color:#e2e8f0; }
    button { margin-top: 20px; width: 100%; padding: 12px; border: none; border-radius: 8px; background: #6366f1; color: white; font-size: 15px; cursor: pointer; }
    button:hover { background: #4f46e5; }
    .msg { margin-top: 16px; padding: 12px; border-radius: 8px; white-space: pre-line; font-size: 13px; }
    .msg.ok { background: #064e3b; color: #6ee7b7; }
    .msg.err { background: #450a0a; color: #fca5a5; }
    .warn { margin-top: 18px; font-size: 12px; color: #fbbf24; }
</style>
</head>
<body>
<div class="box">
    <h1>نصب ربات Gemini ↔ GitHub</h1>
    <p class="sub">اطلاعات زیر رو پر کن، بقیه (فایل .env و webhook) خودکار تنظیم می‌شه.</p>

    <?php if ($message): ?>
        <div class="msg <?= $success ? 'ok' : 'err' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post">
        <label>توکن ربات تلگرام (از @BotFather)</label>
        <input type="text" name="telegram_token" required>

        <label>Gemini API Key</label>
        <input type="text" name="gemini_key" required>

        <label>مدل Gemini (اختیاری)</label>
        <input type="text" name="gemini_model" placeholder="gemini-2.0-flash">

        <label>GitHub Personal Access Token</label>
        <input type="text" name="github_token" required>

        <label>ریپوی پیش‌فرض (owner/repo - اختیاری)</label>
        <input type="text" name="github_repo" placeholder="kiankan/aigeminibot">

        <label>آیدی عددی تلگرام تو (با @userinfobot بگیر)</label>
        <input type="text" name="allowed_user" required>

        <button type="submit">نصب و ثبت Webhook</button>
    </form>
    <?php endif; ?>

    <div class="warn">⚠️ بعد از پایان نصب، پوشه installer رو از روی هاست پاک کن.</div>
</div>
</body>
</html>
