<?php
/**
 * github.php
 * کامیت کردن فایل روی گیت‌هاب با استفاده از GitHub Contents API.
 * https://docs.github.com/en/rest/repos/contents
 */

require_once __DIR__ . '/http.php';

function github_headers(): array
{
    return [
        'Authorization: Bearer ' . GITHUB_TOKEN,
        'Accept: application/vnd.github+json',
        'User-Agent: gemini-github-telegram-bot',
        'Content-Type: application/json',
    ];
}

/**
 * sha فعلی فایل رو می‌گیره (اگه فایل از قبل وجود داشته باشه)، وگرنه null.
 */
function github_get_file_sha(string $repo, string $path, string $branch): ?string
{
    $url = sprintf('https://api.github.com/repos/%s/contents/%s?ref=%s', $repo, rawurlencode($path), urlencode($branch));
    $res = http_request('GET', $url, github_headers());

    if ($res['ok'] && isset($res['body']['sha'])) {
        return $res['body']['sha'];
    }
    return null;
}

/**
 * یک فایل رو در ریپو می‌سازه یا آپدیت می‌کنه (کامیت می‌زنه).
 *
 * @param string $repo    فرم owner/repo
 * @param string $path    مسیر فایل در ریپو، مثلا src/bot.py
 * @param string $content محتوای فایل (متن ساده)
 * @param string $message پیام کامیت
 * @param string $branch  شاخه هدف
 */
function github_commit_file(string $repo, string $path, string $content, string $message, string $branch = 'main'): array
{
    $sha = github_get_file_sha($repo, $path, $branch);

    $payload = [
        'message' => $message,
        'content' => base64_encode($content),
        'branch'  => $branch,
    ];
    if ($sha !== null) {
        $payload['sha'] = $sha; // برای آپدیت فایل موجود لازمه
    }

    $url = sprintf('https://api.github.com/repos/%s/contents/%s', $repo, rawurlencode($path));
    $res = http_request('PUT', $url, github_headers(), json_encode($payload));

    if (!$res['ok']) {
        $msg = is_array($res['body']) ? ($res['body']['message'] ?? json_encode($res['body'])) : (string)$res['body'];
        return ['ok' => false, 'message' => "خطای گیت‌هاب (HTTP {$res['status']}): {$msg}"];
    }

    $commitUrl = $res['body']['commit']['html_url'] ?? ($res['body']['content']['html_url'] ?? null);
    return ['ok' => true, 'message' => 'کامیت با موفقیت ثبت شد.', 'url' => $commitUrl];
}
