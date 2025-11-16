# ====================================================
# InternConnect Database Migration Script
# ====================================================
# This script helps migrate from FreeSQLDatabase to InfinityFree

Write-Host "=== INTERNCONNECT DATABASE MIGRATION HELPER ===" -ForegroundColor Green
Write-Host ""

# Step 1: Check if backup exists
$backupFile = "database/sql3806785.sql"
if (Test-Path $backupFile) {
    Write-Host "✅ Backup file found: $backupFile" -ForegroundColor Green
    $fileSize = (Get-Item $backupFile).Length / 1MB
    Write-Host "   File size: $([math]::Round($fileSize, 2)) MB" -ForegroundColor Cyan
} else {
    Write-Host "❌ Backup file not found: $backupFile" -ForegroundColor Red
    exit 1
}

# Step 2: Display missing columns summary
Write-Host ""
Write-Host "=== MISSING COLUMNS SUMMARY ===" -ForegroundColor Yellow
Write-Host ""
Write-Host "🔴 CRITICAL - host_training_establishment table:" -ForegroundColor Red
Write-Host "   Missing: MOA_FILE_URL, MOA_PUBLIC_ID, MOA_START_DATE, MOA_END_DATE, MOA_UPLOAD_DATE"
Write-Host "   Impact: MOA upload functionality will fail"
Write-Host ""
Write-Host "🟡 MODERATE - session_details table:" -ForegroundColor Yellow  
Write-Host "   Missing: SESSION_ID, SESSION_NAME, START_DATE, END_DATE"
Write-Host "   Impact: Limited session management features"
Write-Host ""
Write-Host "🟢 LOW - interns_attendance table:" -ForegroundColor Green
Write-Host "   Missing: ATTENDANCE_DATE, TIME_IN, TIME_OUT (alternative column names)"
Write-Host "   Impact: Minor compatibility issues"

# Step 3: Instructions for InfinityFree setup
Write-Host ""
Write-Host "=== INFINITYFREE SETUP INSTRUCTIONS ===" -ForegroundColor Magenta
Write-Host ""
Write-Host "1. Sign up for InfinityFree account at: https://infinityfree.net"
Write-Host "2. Create a new MySQL database in cPanel"
Write-Host "3. Note down your database credentials:"
Write-Host "   - Database Host"
Write-Host "   - Database Name" 
Write-Host "   - Database Username"
Write-Host "   - Database Password"

# Step 4: Database restoration commands
Write-Host ""
Write-Host "=== RESTORATION COMMANDS ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "After getting your InfinityFree credentials, run these commands:"
Write-Host ""
Write-Host "# Option 1: Using phpMyAdmin (Recommended for beginners)" -ForegroundColor Green
Write-Host "1. Open InfinityFree cPanel → phpMyAdmin"
Write-Host "2. Select your database"
Write-Host "3. Go to Import tab"
Write-Host "4. Choose file: database/sql3806785.sql"
Write-Host "5. Click 'Go' to import"
Write-Host "6. Go to SQL tab and paste contents from database_restoration.sql"
Write-Host "7. Click 'Go' to add missing columns"
Write-Host ""
Write-Host "# Option 2: Using MySQL command line" -ForegroundColor Blue
Write-Host "mysql -h [YOUR_HOST] -u [YOUR_USERNAME] -p [YOUR_DATABASE] < database/sql3806785.sql"
Write-Host "mysql -h [YOUR_HOST] -u [YOUR_USERNAME] -p [YOUR_DATABASE] < database_restoration.sql"

# Step 5: Configuration update helper
Write-Host ""
Write-Host "=== CONFIGURATION UPDATE ===" -ForegroundColor Yellow
Write-Host ""

$configFile = "database/database.php"
if (Test-Path $configFile) {
    Write-Host "Current database configuration in $configFile needs updating."
    Write-Host ""
    Write-Host "Replace these lines (around line 22-25):" -ForegroundColor Red
    Write-Host "        `$this->servername = getenv('DB_HOST') ?: 'sql3.freesqldatabase.com:3306';"
    Write-Host "        `$this->username = getenv('DB_USERNAME') ?: 'sql3806785';"
    Write-Host "        `$this->password = getenv('DB_PASSWORD') ?: 'DAl9FGjxvF';"
    Write-Host "        `$this->dbname = getenv('DB_NAME') ?: 'sql3806785';"
    Write-Host ""
    Write-Host "With your InfinityFree credentials:" -ForegroundColor Green
    Write-Host "        `$this->servername = getenv('DB_HOST') ?: '[YOUR_INFINITYFREE_HOST]:3306';"
    Write-Host "        `$this->username = getenv('DB_USERNAME') ?: '[YOUR_INFINITYFREE_USERNAME]';"
    Write-Host "        `$this->password = getenv('DB_PASSWORD') ?: '[YOUR_INFINITYFREE_PASSWORD]';"
    Write-Host "        `$this->dbname = getenv('DB_NAME') ?: '[YOUR_INFINITYFREE_DATABASE]';"
    Write-Host ""
    Write-Host "Also update the fallback configuration (around line 36-40)."
} else {
    Write-Host "❌ Configuration file not found: $configFile" -ForegroundColor Red
}

# Step 6: Testing checklist
Write-Host ""
Write-Host "=== POST-MIGRATION TESTING CHECKLIST ===" -ForegroundColor Green
Write-Host ""
Write-Host "After restoration, test these features:"
Write-Host "✓ Admin login functionality"
Write-Host "✓ Student dashboard access"
Write-Host "✓ HTE creation with MOA upload (CRITICAL)"
Write-Host "✓ Attendance tracking"
Write-Host "✓ Weekly report submission"
Write-Host "✓ Evaluation system"

# Step 7: Backup verification
Write-Host ""
Write-Host "=== BACKUP DATA VERIFICATION ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Your November 13, 2025 backup contains:"

# Count records in backup (approximate)
$backupContent = Get-Content $backupFile -Raw
$coordinatorInserts = ([regex]'INSERT INTO `coordinator`').Matches($backupContent).Count
$hteInserts = ([regex]'INSERT INTO `host_training_establishment`').Matches($backupContent).Count

Write-Host "• Coordinators: ~$coordinatorInserts records"
Write-Host "• Host Training Establishments: ~$hteInserts records"
Write-Host "• Data freshness: 3 days old (Nov 13 → Nov 16)"

Write-Host ""
Write-Host "=== READY TO PROCEED? ===" -ForegroundColor Magenta
Write-Host ""
Write-Host "Have you:"
Write-Host "1. ✓ Signed up for InfinityFree?"
Write-Host "2. ✓ Created a MySQL database?"
Write-Host "3. ✓ Got your database credentials?"
Write-Host ""
Write-Host "If yes, proceed with the restoration commands above!"
Write-Host "If no, complete the InfinityFree setup first."
Write-Host ""
Write-Host "Files created for you:"
Write-Host "• database_restoration.sql - Run this after importing backup"
Write-Host "• This migration guide - Keep for reference"