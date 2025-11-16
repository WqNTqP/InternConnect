# ====================================================
# InternConnect to InfinityFree Migration - READY TO EXECUTE
# ====================================================

Write-Host "=== INTERNCONNECT → INFINITYFREE MIGRATION ===" -ForegroundColor Green
Write-Host ""

# Database credentials
$dbHost = "sql102.infinityfree.com"
$dbUser = "if0_40429035"
$dbPass = "bRXtz7w8GIW8X" 
$dbName = "if0_40429035_XXX"

Write-Host "✅ InfinityFree Database Configured:" -ForegroundColor Green
Write-Host "   Host: $dbHost"
Write-Host "   User: $dbUser"
Write-Host "   Database: $dbName"
Write-Host "   Password: [CONFIGURED]"
Write-Host ""

# Check backup file
$backupFile = "database/sql3806785.sql"
if (Test-Path $backupFile) {
    $fileSize = (Get-Item $backupFile).Length / 1MB
    Write-Host "✅ Backup file ready: $([math]::Round($fileSize, 2)) MB" -ForegroundColor Green
} else {
    Write-Host "❌ Backup file missing: $backupFile" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=== STEP 1: IMPORT YOUR BACKUP ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "🔧 Option A: Using phpMyAdmin (Recommended)" -ForegroundColor Yellow
Write-Host "1. Go to your InfinityFree cPanel"
Write-Host "2. Open phpMyAdmin"
Write-Host "3. Select database: $dbName"
Write-Host "4. Go to Import tab"
Write-Host "5. Choose file: $backupFile"
Write-Host "6. Click 'Go' and wait for completion"
Write-Host ""
Write-Host "🔧 Option B: Using MySQL Command Line" -ForegroundColor Yellow
Write-Host "mysql -h $dbHost -u $dbUser -p$dbPass $dbName < `"$((Get-Location).Path)\$backupFile`""
Write-Host ""

Write-Host "=== STEP 2: ADD MISSING MOA COLUMNS ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "After importing backup, run this SQL (in phpMyAdmin SQL tab):"
Write-Host ""
Write-Host "ALTER TABLE ``host_training_establishment``" -ForegroundColor Yellow
Write-Host "  ADD COLUMN ``MOA_FILE_URL`` varchar(500) DEFAULT NULL,"
Write-Host "  ADD COLUMN ``MOA_PUBLIC_ID`` varchar(255) DEFAULT NULL,"
Write-Host "  ADD COLUMN ``MOA_START_DATE`` date DEFAULT NULL,"
Write-Host "  ADD COLUMN ``MOA_END_DATE`` date DEFAULT NULL,"
Write-Host "  ADD COLUMN ``MOA_UPLOAD_DATE`` timestamp NULL DEFAULT NULL;"
Write-Host ""

Write-Host "=== STEP 3: VERIFY RESTORATION ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Run this verification query:"
Write-Host "SELECT COUNT(*) as coordinator_count FROM coordinator;" -ForegroundColor Yellow
Write-Host "SELECT COUNT(*) as hte_count FROM host_training_establishment;" 
Write-Host "DESCRIBE host_training_establishment;" 
Write-Host ""

Write-Host "Expected results:" -ForegroundColor Green
Write-Host "• coordinator_count: Should be > 0"
Write-Host "• hte_count: Should be > 0" 
Write-Host "• host_training_establishment should show MOA columns"
Write-Host ""

Write-Host "=== CONFIGURATION UPDATES ===" -ForegroundColor Magenta
Write-Host ""
Write-Host "✅ Updated files:" -ForegroundColor Green
Write-Host "• database/database.php - Production credentials updated"
Write-Host "• .env.example - Template updated with InfinityFree"
Write-Host "• .env.infinityfree - New config file created"
Write-Host ""

Write-Host "=== TESTING CHECKLIST ===" -ForegroundColor Yellow
Write-Host ""
Write-Host "After migration, test these critical features:"
Write-Host "□ Admin login (test credentials from backup)"
Write-Host "□ Student dashboard access"
Write-Host "□ HTE creation with MOA upload (MOST CRITICAL)"
Write-Host "□ Attendance tracking"
Write-Host "□ Weekly reports"
Write-Host "□ Evaluation system"
Write-Host ""

Write-Host "=== TROUBLESHOOTING ===" -ForegroundColor Red
Write-Host ""
Write-Host "If you get connection errors:"
Write-Host "1. Verify database name is exactly: $dbName"
Write-Host "2. Check if InfinityFree requires SSL (add 'sslmode=require')"
Write-Host "3. Ensure remote MySQL access is enabled in cPanel"
Write-Host "4. Contact InfinityFree support if issues persist"
Write-Host ""

Write-Host "=== READY? ===" -ForegroundColor Green
Write-Host ""
Write-Host "Your database configuration is updated and ready."
Write-Host "Proceed with Step 1 above to import your backup!"
Write-Host ""

# Test local connection (if MySQL client available)
Write-Host "=== CONNECTION TEST ===" -ForegroundColor Cyan
if (Get-Command mysql -ErrorAction SilentlyContinue) {
    Write-Host "Testing connection to InfinityFree..."
    try {
        $testResult = & mysql -h $dbHost -u $dbUser -p$dbPass -e "SELECT 1;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✅ Connection successful!" -ForegroundColor Green
        } else {
            Write-Host "❌ Connection failed: $testResult" -ForegroundColor Red
        }
    } catch {
        Write-Host "⚠️ Could not test connection (mysql client issues)" -ForegroundColor Yellow
    }
} else {
    Write-Host "⚠️ MySQL client not found - use phpMyAdmin instead" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Files created for you:"
Write-Host "• database_restoration.sql - Complete restoration script"
Write-Host "• .env.infinityfree - InfinityFree configuration" 
Write-Host "• This migration script - Step-by-step guide"