#!/usr/bin/env bash
#
# install.sh
# نصب سریع ربات روی سرور (VPS). این اسکریپت:
#   1) وجود php-cli و curl رو چک می‌کنه
#   2) از کاربر توکن‌ها رو می‌پرسه و فایل .env رو می‌سازه
#   3) ربات رو روی پورت 2000 با php -S بالا میاره (به صورت پایدار با systemd، اگه دسترسی root باشه؛
#      وگرنه با nohup در پس‌زمینه)
#   4) راهنمای گرفتن HTTPS جلوی پورت 2000 (لازم برای webhook تلگرام) رو نشون می‌ده
#
# استفاده:
#   chmod +x install.sh
#   ./install.sh
#
set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PORT=2000
SERVICE_NAME="aigeminibot"

echo "== نصب ربات Gemini ↔ GitHub =="
echo "مسیر پروژه: $PROJECT_DIR"
echo

# --- 1) بررسی نیازمندی‌ها ---
if ! command -v php >/dev/null 2>&1; then
    echo "❌ php-cli پیدا نشد."
    echo "   نصبش کن، مثلاً روی Ubuntu/Debian:"
    echo "   sudo apt update && sudo apt install -y php-cli php-curl"
    exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
    echo "❌ curl پیدا نشد. نصبش کن: sudo apt install -y curl"
    exit 1
fi

echo "✅ php و curl موجودن."
echo

# --- 2) ساخت فایل .env ---
ENV_FILE="$PROJECT_DIR/.env"

if [ -f "$ENV_FILE" ]; then
    echo "فایل .env از قبل وجود داره."
    read -p "می‌خوای دوباره بازنویسیش کنی؟ (y/N): " OVERWRITE
    OVERWRITE=${OVERWRITE:-N}
else
    OVERWRITE="y"
fi

if [[ "$OVERWRITE" =~ ^[Yy]$ ]]; then
    read -p "توکن ربات تلگرام (از @BotFather): " TELEGRAM_BOT_TOKEN
    read -p "Gemini API Key: " GEMINI_API_KEY
    read -p "مدل Gemini [پیش‌فرض gemini-2.0-flash]: " GEMINI_MODEL
    GEMINI_MODEL=${GEMINI_MODEL:-gemini-2.0-flash}
    read -p "GitHub Personal Access Token: " GITHUB_TOKEN
    read -p "ریپوی پیش‌فرض owner/repo [اختیاری]: " GITHUB_DEFAULT_REPO
    read -p "آیدی عددی تلگرام تو (با @userinfobot بگیر): " ALLOWED_TELEGRAM_USER_ID

    cat > "$ENV_FILE" <<EOF
TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
GEMINI_API_KEY=${GEMINI_API_KEY}
GEMINI_MODEL=${GEMINI_MODEL}
GITHUB_TOKEN=${GITHUB_TOKEN}
GITHUB_DEFAULT_REPO=${GITHUB_DEFAULT_REPO}
ALLOWED_TELEGRAM_USER_ID=${ALLOWED_TELEGRAM_USER_ID}
EOF
    chmod 600 "$ENV_FILE"
    echo "✅ .env ساخته شد."
else
    echo "از .env موجود استفاده می‌شه."
fi
echo

# --- 3) بالا آوردن ربات روی پورت 2000 ---
IS_ROOT=false
if [ "$(id -u)" -eq 0 ]; then
    IS_ROOT=true
fi

if $IS_ROOT && command -v systemctl >/dev/null 2>&1; then
    echo "درحال ساخت سرویس systemd برای اجرای دائمی روی پورت $PORT ..."
    cat > "/etc/systemd/system/${SERVICE_NAME}.service" <<EOF
[Unit]
Description=Gemini-GitHub Telegram Bot (PHP)
After=network.target

[Service]
Type=simple
WorkingDirectory=${PROJECT_DIR}
ExecStart=$(command -v php) -S 0.0.0.0:${PORT} webhook.php
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
    systemctl daemon-reload
    systemctl enable "${SERVICE_NAME}"
    systemctl restart "${SERVICE_NAME}"
    echo "✅ سرویس ${SERVICE_NAME} فعال شد و روی پورت ${PORT} در حال اجراست."
    echo "   وضعیت: systemctl status ${SERVICE_NAME}"
    echo "   لاگ‌ها:  journalctl -u ${SERVICE_NAME} -f"
else
    echo "درحال اجرا در پس‌زمینه با nohup (چون دسترسی root/systemd نیست)..."
    nohup php -S 0.0.0.0:${PORT} "$PROJECT_DIR/webhook.php" > "$PROJECT_DIR/bot.log" 2>&1 &
    echo $! > "$PROJECT_DIR/.bot.pid"
    echo "✅ ربات روی پورت ${PORT} اجرا شد (PID: $(cat "$PROJECT_DIR/.bot.pid"))."
    echo "   لاگ‌ها: tail -f $PROJECT_DIR/bot.log"
    echo "   توقف:   kill \$(cat $PROJECT_DIR/.bot.pid)"
fi
echo

# --- 4) راهنمای HTTPS و ثبت webhook ---
cat <<'EOM'
========================================================
مرحله بعد: تلگرام فقط آدرس HTTPS رو به عنوان webhook قبول می‌کنه.
یکی از این دو راه رو برای گذاشتن HTTPS جلوی پورت 2000 انتخاب کن:

  گزینه سریع (بدون دامنه) - Cloudflare Tunnel:
    cloudflared tunnel --url http://localhost:2000
    (یک آدرس https://xxxx.trycloudflare.com بهت می‌ده)

  گزینه دائمی (با دامنه) - Nginx + Certbot:
    Nginx رو reverse-proxy کن به 127.0.0.1:2000 (نمونه تنظیمات در README.md)

بعد از گرفتن آدرس HTTPS، این دستور رو بزن تا webhook ثبت بشه:
    php set_webhook.php https://YOUR-HTTPS-ADDRESS/webhook.php
========================================================
EOM
