@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul

echo ====================================
echo   رفع قاعدة البيانات للسيرفر
echo ====================================

cd /d "%~dp0.."

set "SERVER_IP=187.124.35.87"
set "SERVER_USER=root"
set "SERVER_PATH=/var/www/erp"
set "BACKUP_DIR=scripts\backups"

if not exist "%BACKUP_DIR%" (
    echo [خطأ] مجلد النسخ غير موجود. شغّل أولاً: scripts\export-local-db.bat
    pause
    exit /b 1
)

REM أحدث ملف backup_*.sql
set "LATEST="
for /f "delims=" %%f in ('dir /b /od "%BACKUP_DIR%\backup_*.sql" 2^>nul') do set "LATEST=%%f"

if "%LATEST%"=="" (
    echo [خطأ] لا يوجد ملف backup_*.sql في %BACKUP_DIR%
    pause
    exit /b 1
)

echo أحدث نسخة: %LATEST%
echo السيرفر: %SERVER_USER%@%SERVER_IP%
echo.

echo [1/2] جاري رفع الملف...
scp "%BACKUP_DIR%\%LATEST%" %SERVER_USER%@%SERVER_IP%:/tmp/db_backup.sql
if errorlevel 1 (
    echo [خطأ] فشل الرفع. تأكد من SSH وكلمة مرور السيرفر.
    pause
    exit /b 1
)

echo.
echo [2/2] جاري الاستيراد على السيرفر...
ssh %SERVER_USER%@%SERVER_IP% "chmod +x %SERVER_PATH%/scripts/sync-database.sh 2>/dev/null; bash %SERVER_PATH%/scripts/sync-database.sh"
if errorlevel 1 (
    echo [خطأ] فشل الاستيراد. راجع مخرجات SSH أعلاه.
    pause
    exit /b 1
)

echo.
echo تم الرفع والاستيراد بنجاح!
echo تحقق من: http://firstclickerp.top
pause
