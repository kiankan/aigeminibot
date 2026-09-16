# ربات تلگرام Gemini ↔ GitHub (PHP)

پیام تلگرام → Gemini API → تولید محتوا/کد → کامیت روی گیت‌هاب.

## پیش‌نیاز
- PHP 8+ با extension `curl` فعال (روی اکثر هاست‌ها پیش‌فرض فعاله)
- بدون نیاز به Composer یا هیچ کتابخونه‌ی خارجی

دو راه نصب داری: **نصاب گرافیکی** (`installer/index.php`) برای هاست، و **اسکریپت خودکار** (`install.sh`) برای سرور. هر دو راه دستی (`.env.example`) هم زیرشون توضیح داده شده اگه بخوای خودت دستی تنظیم کنی.

---

## حالت ۱: روی هاست اشتراکی (با نصاب گرافیکی)

1. کل پوشه پروژه رو (همه فایل‌های `.php`، پوشه `installer`، `.htaccess`) داخل `public_html` یا زیرپوشه‌ای مثل `public_html/aigeminibot` آپلود کن.
2. از مرورگر برو به آدرس پوشه installer، مثلاً:
   ```
   https://yourdomain.com/aigeminibot/installer/
   ```
3. فرم رو پر کن:
   - توکن ربات تلگرام (از @BotFather)
   - Gemini API Key (از https://aistudio.google.com/apikey)
   - مدل Gemini (اختیاری، پیش‌فرض `gemini-2.0-flash`)
   - GitHub Personal Access Token
   - ریپوی پیش‌فرض `owner/repo` (اختیاری)
   - آیدی عددی تلگرام خودت (از @userinfobot)
4. دکمه «نصب و ثبت Webhook» رو بزن. نصاب خودش فایل `.env` رو می‌سازه و آدرس `webhook.php` رو به تلگرام معرفی می‌کنه — نیازی به دستور دستی نیست.
5. **مهم:** بعد از دیدن پیام موفقیت، پوشه `installer` رو کامل از روی هاست پاک کن (چون بدون رمز، هرکسی که آدرسش رو پیدا کنه می‌تونه تنظیمات رو عوض کنه).

### نصب دستی (به‌جای نصاب گرافیکی)
اگه ترجیح می‌دی دستی تنظیم کنی:
```
cp .env.example .env
```
مقادیر بالا رو داخلش پر کن، بعد این دستور رو بزن تا webhook ثبت بشه:
```
php set_webhook.php https://yourdomain.com/aigeminibot/webhook.php
```

---

## حالت ۲: روی سرور خودت (با install.sh، پورت 2000)

```bash
git clone https://github.com/kiankan/aigeminibot.git
cd aigeminibot
chmod +x install.sh
./install.sh
```

این اسکریپت به‌صورت خودکار:
1. وجود `php-cli` و `curl` رو چک می‌کنه.
2. توکن‌ها (تلگرام، Gemini، GitHub، آیدی مجاز) رو ازت می‌پرسه و فایل `.env` رو می‌سازه.
3. ربات رو روی **پورت 2000** بالا می‌آره:
   - اگه `root` باشی و `systemd` موجود باشه → یک سرویس دائمی به اسم `aigeminibot` می‌سازه (`systemctl status aigeminibot`، `journalctl -u aigeminibot -f`).
   - وگرنه → با `nohup` در پس‌زمینه اجرا می‌کنه (لاگ در `bot.log`، توقف با `kill $(cat .bot.pid)`).
4. در آخر راهنمای گرفتن HTTPS جلوی پورت 2000 رو نشون می‌ده (چون Webhook تلگرام فقط HTTPS قبول می‌کنه):

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

بعد از گرفتن آدرس HTTPS، این دستور رو بزن تا webhook ثبت بشه:
```bash
php set_webhook.php https://YOUR-HTTPS-ADDRESS/webhook.php
```

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
