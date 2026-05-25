# نقل قاعدة البيانات من المحلي إلى السيرفر

## المتطلبات

- **محلياً:** Laragon أو XAMPP مع **MySQL** (ليس SQLite)
- `backend/.env` مضبوط على `DB_CONNECTION=mysql`
- **على Windows:** OpenSSH (`scp` و `ssh`) — مثبت مع Windows 10+
- **على السيرفر:** Ubuntu 22.04، المشروع في `/var/www/erp`

---

## الطريقة السريعة (موصى بها)

### 1) على جهازك — تصدير

```batch
scripts\export-local-db.bat
```

يُنشئ ملفاً في `scripts\backups\backup_YYYYMMDD_HHMMSS.sql`

### 2) على جهازك — رفع واستيراد

```batch
scripts\upload-db-to-server.bat
```

يرفع الملف إلى `/tmp/db_backup.sql` ويشغّل `scripts/sync-database.sh` على السيرفر.

---

## يدوياً (خطوة بخطوة)

### على جهازك

```batch
cd D:\erp projects\first click
scripts\export-local-db.bat
scp scripts\backups\backup_YYYYMMDD_HHMMSS.sql root@187.124.35.87:/tmp/db_backup.sql
```

### على السيرفر (Hostinger Terminal)

```bash
chmod +x /var/www/erp/scripts/sync-database.sh
bash /var/www/erp/scripts/sync-database.sh
```

---

## بديل: Artisan (من مجلد backend)

```bash
cd backend
php artisan tenant:export --full --output=storage/app/exports/full_backup.sql
```

تصدير شركة واحدة فقط:

```bash
php artisan tenant:export --tenant=1 --output=storage/app/exports/tenant1.sql
```

---

## بعد الاستيراد

1. تحقق من الدخول: `https://firstclickerp.top`
2. استخدم **نفس معرف الشركة** الذي على المحلي (`first-company` أو غيره)
3. **لا ترفع** ملف `backend/.env` إلى GitHub — بيانات السيرفر تبقى على السيرفر فقط
4. ملف `.env` على السيرفر يحتفظ بـ `APP_KEY` الحالي — لا تستبدله بملف المحلي بعد الاستيراد

---

## استكشاف الأخطاء

| المشكلة | الحل |
|---------|------|
| SQLite في .env | غيّر إلى MySQL في Laragon ثم صدّر |
| mysqldump غير موجود | ثبّت Laragon MySQL أو أضف المسار في `export-local-db.bat` |
| فشل scp/ssh | تحقق من IP وكلمة مرور root |
| خطأ MySQL عند الاستيراد | أرسل آخر 20 سطر من مخرجات `sync-database.sh` |
| اشتراك منتهٍ | `php artisan db:seed --class=DemoDataSeeder --force` أو تجديد من لوحة المشرف |

---

## نسخة احتياطية قبل الاستيراد

السكريبت `sync-database.sh` يحفظ تلقائياً في:

`/tmp/backup_before_import_YYYYMMDD_HHMMSS.sql`
