#!/bin/bash
# استيراد نسخة MySQL مرفوعة إلى /tmp/db_backup.sql على السيرفر
set -euo pipefail

echo "===================================="
echo "  استيراد قاعدة البيانات"
echo "===================================="

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT/backend"

if [[ ! -f .env ]]; then
  echo "[خطأ] backend/.env غير موجود"
  exit 1
fi

if [[ ! -f /tmp/db_backup.sql ]]; then
  echo "[خطأ] /tmp/db_backup.sql غير موجود. ارفع الملف أولاً عبر upload-db-to-server.bat"
  exit 1
fi

read_env() {
  local key="$1"
  grep -E "^${key}=" .env | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^["'\'']//;s/["'\'']$//'
}

DB_NAME="$(read_env DB_DATABASE)"
DB_USER="$(read_env DB_USERNAME)"
DB_PASS="$(read_env DB_PASSWORD)"
DB_HOST="$(read_env DB_HOST)"
DB_PORT="$(read_env DB_PORT)"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

if [[ -z "$DB_NAME" || -z "$DB_USER" ]]; then
  echo "[خطأ] DB_DATABASE أو DB_USERNAME غير مضبوطين في .env"
  exit 1
fi

echo "قاعدة البيانات: $DB_NAME"
echo "المستخدم: $DB_USER"
echo "الملف: /tmp/db_backup.sql ($(du -h /tmp/db_backup.sql | cut -f1))"
echo ""

mysql_cmd() {
  MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$@"
}

mysqldump_cmd() {
  MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$@"
}

# وضع الصيانة
php artisan down --retry=60 || true

BACKUP_BEFORE="/tmp/backup_before_import_$(date +%Y%m%d_%H%M%S).sql"
echo "جاري أخذ نسخة احتياطية للبيانات الحالية..."
mysqldump_cmd --single-transaction --routines --triggers "$DB_NAME" > "$BACKUP_BEFORE"
echo "تم: $BACKUP_BEFORE"

echo "جاري استيراد البيانات الجديدة..."
mysql_cmd "$DB_NAME" < /tmp/db_backup.sql
echo "تم الاستيراد."

echo "جاري تشغيل migrations..."
php artisan migrate --force

echo "مسح الكاش..."
php artisan optimize:clear
php artisan config:cache

php artisan up

echo ""
echo "===================================="
echo "  تم الاستيراد بنجاح"
echo "===================================="
echo "تحقق من الموقع: https://firstclickerp.top"
