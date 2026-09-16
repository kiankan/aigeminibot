# ربات تلگرام Gemini ↔ GitHub (PHP)

پیام تلگرام → Gemini API → تولید محتوا/کد → کامیت روی گیت‌هاب.

## پیش‌نیاز
- PHP 8+ با extension `curl` فعال (روی اکثر هاست‌ها پیش‌فرض فعاله)
- بدون نیاز به Composer یا هیچ کتابخونه‌ی خارجی

## 1) تنظیم فایل .env
```
cp .env.example .env
```
و مقادیر رو پر کن:
- `TELEGRAM_BOT_TOKEN`: از @BotFather
- `GEMINI_API_KEY`: از https://aistudio.google.com/apikey
- `GITHUB_TOKEN`: Personal Access Token با اسکوپ نوشتن روی ریپو
- `ALLOWED_TELEGRAM_USER_ID`: آیدی عددی خودت (با @userinfobot بگیر) تا فقط تو بتونی از ربات استفاده کنی

---

## حالت ۱: روی هاست اشتراکی

1. کل پوشه پروژه (همه فایل‌های `.php` + `.env` + `.htaccess`) رو داخل `public_html` (یا زیرپوشه‌ای مثل `public_html/bot`) آپلود کن.
2. فایل `.htaccess` از دسترسی مستقیم به `.env` و فایل‌های state جلوگیری می‌کنه — حتماً همراه بقیه فایل‌ها آپلود بشه.
3. از روی همون سرور یا از لپ‌تاپت (با PHP نصب‌شده محلی) دستور زیر رو بزن تا webhook ثبت بشه:
   ```
   php set_webhook.php https://yourdomain.com/bot/webhook.php
   ```
4. تمام. حالا هر پیام تلگرام مستقیم به `webhook.php` روی هاست می‌ره.

---

## حالت ۲: روی سرور خودت (پورت 2000 برای همه‌چیز)

تلگرام فقط آدرس **HTTPS** رو به عنوان webhook قبول می‌کنه، پس پورت 2000 باید پشت یک HTTPS بیاد. ساده‌ترین راه:

### الف) اجرای PHP روی پورت 2000
```bash
cd gemini_github_bot_php
php -S 0.0.0.0:2000 webhook.php
```
(برای اجرای دائمی در پس‌زمینه از `screen`, `tmux`, `systemd`, یا `pm2` استفاده کن.)

### ب) گرفتن HTTPS جلوی پورت 2000
دو گزینه:

**گزینه سریع (بدون دامنه) - Cloudflare Tunnel:**
```bash
cloudflared tunnel --url http://localhost:2000
```
یک آدرس `https://xxxx.trycloudflare.com` بهت می‌ده.

**گزینه دائمی (با دامنه) - Nginx + Certbot:**
Nginx رو طوری تنظیم کن که پورت 443 (https) رو reverse-proxy کنه به `127.0.0.1:2000`:
```nginx
server {
    listen 443 ssl;
    server_name yourdomain.com;
    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:2000;
    }
}
```

### ج) ثبت webhook
```bash
php set_webhook.php https://yourdomain.com/
```
یا آدرس Cloudflare Tunnel که گرفتی.

---

## دستورات ربات در تلگرام
```
/start                          راهنما
/repo owner/repo                تعیین ریپوی هدف
<هر متنی>                       به جمینی فرستاده می‌شه و پاسخ ذخیره می‌شه
/commit path/فایل.ext [branch]  آخرین پاسخ جمینی رو کامیت می‌کنه (پیش‌فرض branch = main)
```

مثال کامل:
```
/repo mehdi/my-project
یک تابع پایتون برای محاسبه فیبوناچی بنویس
/commit fib.py
```

## نکات امنیتی
- `.env` هرگز نباید public باشه — `.htaccess` این رو روی Apache هندل می‌کنه؛ اگه Nginx استفاده می‌کنی باید خودت `location ~ /\.env { deny all; }` اضافه کنی.
- `ALLOWED_TELEGRAM_USER_ID` رو حتماً پر کن وگرنه هر کسی که آیدی ربات رو پیدا کنه می‌تونه ازش (و از GitHub token تو) استفاده کنه.
- توکن‌هایی که قبلاً جایی (چت، کد عمومی و غیره) پیست کردی رو revoke/rotate کن.
