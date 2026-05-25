@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul

echo ====================================
echo   تصدير قاعدة البيانات المحلية
echo ====================================

REM الانتقال لجذر المشروع (المجلد الأب لـ scripts)
cd /d "%~dp0.."
if not exist "backend\.env" (
    echo [خطأ] لم يُعثر على backend\.env
    pause
    exit /b 1
)

set "ENV_FILE=backend\.env"
set "BACKUP_DIR=scripts\backups"
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM قراءة إعدادات قاعدة البيانات من .env
set "DB_CONN="
set "DB_NAME="
set "DB_USER="
set "DB_PASS="
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"

for /f "usebackq tokens=1,* delims==" %%a in (`findstr /r "^DB_CONNECTION= ^DB_DATABASE= ^DB_USERNAME= ^DB_PASSWORD= ^DB_HOST= ^DB_PORT= " "%ENV_FILE%"`) do (
    set "key=%%a"
    set "val=%%b"
    set "val=!val:"=!"
    if "!key!"=="DB_CONNECTION" set "DB_CONN=!val!"
    if "!key!"=="DB_DATABASE" set "DB_NAME=!val!"
    if "!key!"=="DB_USERNAME" set "DB_USER=!val!"
    if "!key!"=="DB_PASSWORD" set "DB_PASS=!val!"
    if "!key!"=="DB_HOST" set "DB_HOST=!val!"
    if "!key!"=="DB_PORT" set "DB_PORT=!val!"
)

if /i "%DB_CONN%"=="sqlite" (
    echo [خطأ] المشروع مضبوط على SQLite. للنقل للسيرفر يجب استخدام MySQL محلياً.
    echo         عدّل backend\.env ثم أعد التصدير.
    pause
    exit /b 1
)

if "%DB_NAME%"=="" (
    echo [خطأ] DB_DATABASE غير موجود في backend\.env
    pause
    exit /b 1
)

REM توقيت الملف: YYYYMMDD_HHMMSS
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "TS=%%i"
set "OUT_FILE=%BACKUP_DIR%\backup_%TS%.sql"

echo قاعدة البيانات: %DB_NAME%
echo المستخدم: %DB_USER%
echo الملف: %OUT_FILE%
echo.

REM البحث عن mysqldump (Laragon / XAMPP / PATH)
set "MYSQLDUMP="
if exist "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe" set "MYSQLDUMP=C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe"
if "%MYSQLDUMP%"=="" if exist "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe" set "MYSQLDUMP=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"
if "%MYSQLDUMP%"=="" if exist "C:\xampp\mysql\bin\mysqldump.exe" set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
if "%MYSQLDUMP%"=="" (
    for /f "delims=" %%p in ('where mysqldump 2^>nul') do set "MYSQLDUMP=%%p"
)

if "%MYSQLDUMP%"=="" (
    echo [خطأ] لم يُعثر على mysqldump. ثبّت Laragon/MySQL أو أضف mysqldump إلى PATH.
    pause
    exit /b 1
)

echo جاري التصدير...
"%MYSQLDUMP%" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% ^
  --single-transaction --routines --triggers --set-charset --default-character-set=utf8mb4 ^
  %DB_NAME% > "%OUT_FILE%"

if errorlevel 1 (
    echo [خطأ] فشل mysqldump. تحقق من اسم المستخدم وكلمة المرور وخدمة MySQL.
    pause
    exit /b 1
)

for %%A in ("%OUT_FILE%") do set "SIZE=%%~zA"
echo.
echo تم التصدير بنجاح!
echo الملف: %OUT_FILE%
echo الحجم: %SIZE% بايت
echo.
echo الخطوة التالية: scripts\upload-db-to-server.bat
pause
