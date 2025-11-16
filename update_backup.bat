@echo off
REM Replace old backup with new Railway backup
echo.
echo =====================================
echo InternConnect Database Backup Update
echo =====================================
echo.

REM Get the newest railway backup file
for /f "delims=" %%i in ('dir "database\railway_backup_*.sql" /b /od') do set "newest=%%i"

if not defined newest (
    echo ❌ No Railway backup files found!
    echo Please run: php export_railway_backup.php first
    pause
    exit /b 1
)

echo 📁 Found newest backup: %newest%
echo.

REM Backup the old file first
if exist "database\sql3806785.sql" (
    echo 🔄 Backing up old database file...
    copy "database\sql3806785.sql" "database\sql3806785_old_%date:~-4,4%-%date:~-10,2%-%date:~-7,2%.sql"
    echo ✅ Old backup saved as: sql3806785_old_%date:~-4,4%-%date:~-10,2%-%date:~-7,2%.sql
)

REM Replace with new backup
echo 🔄 Replacing old backup with new Railway backup...
copy "database\%newest%" "database\sql3806785.sql"

if %errorlevel% equ 0 (
    echo ✅ Successfully updated database backup!
    echo.
    echo 📋 Summary:
    echo    - Old backup: Saved as backup
    echo    - New backup: %newest% → sql3806785.sql
    echo    - Features: Clean data, MOA support, auto-increment fixes
    echo.
) else (
    echo ❌ Failed to update backup file!
)

echo Press any key to continue...
pause >nul