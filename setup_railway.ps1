# ====================================================
# Railway Database Setup and Migration Script
# ====================================================

Write-Host "=== RAILWAY DATABASE MIGRATION GUIDE ===" -ForegroundColor Green
Write-Host ""

Write-Host "📋 STEP 1: Get Railway Credentials" -ForegroundColor Cyan
Write-Host "After your Railway MySQL deploys:"
Write-Host "1. Click on the MySQL service in Railway dashboard"
Write-Host "2. Go to 'Variables' or 'Connect' tab"  
Write-Host "3. Copy these values:"
Write-Host "   - MYSQL_HOST (e.g., containers-us-west-123.railway.app)"
Write-Host "   - MYSQL_PORT (usually 3306)"
Write-Host "   - MYSQL_USER (usually root)"
Write-Host "   - MYSQL_PASSWORD (long random string)"
Write-Host "   - MYSQL_DATABASE (usually railway)"
Write-Host ""

Write-Host "📋 STEP 2: Update Configuration Template" -ForegroundColor Cyan
Write-Host "I'll create a template you can fill with Railway credentials:"
Write-Host ""

# Create Railway config template
$railwayTemplate = @"
# Railway Database Configuration Template
# Replace the placeholder values with your actual Railway credentials

# Railway Production Database
DB_HOST=[RAILWAY_MYSQL_HOST]:3306
DB_USERNAME=[RAILWAY_MYSQL_USER]  
DB_PASSWORD=[RAILWAY_MYSQL_PASSWORD]
DB_NAME=[RAILWAY_MYSQL_DATABASE]

# Example with real values:
# DB_HOST=containers-us-west-123.railway.app:3306
# DB_USERNAME=root
# DB_PASSWORD=abc123xyz789
# DB_NAME=railway
"@

$railwayTemplate | Out-File -FilePath ".env.railway" -Encoding UTF8
Write-Host "✅ Created: .env.railway (template file)"

Write-Host ""
Write-Host "📋 STEP 3: Test Railway Connection" -ForegroundColor Cyan
Write-Host "After getting credentials, run: php test_railway_connection.php"
Write-Host ""

Write-Host "📋 STEP 4: Import Backup Data" -ForegroundColor Cyan  
Write-Host "Use this command with your Railway credentials:"
Write-Host 'mysql -h [RAILWAY_HOST] -P 3306 -u [RAILWAY_USER] -p[RAILWAY_PASSWORD] [RAILWAY_DATABASE] < "database/sql3806785.sql"'
Write-Host ""

Write-Host "📋 STEP 5: Add Missing MOA Columns" -ForegroundColor Cyan
Write-Host "After import, connect to Railway MySQL and run:"
Write-Host "ALTER TABLE host_training_establishment"
Write-Host "  ADD COLUMN MOA_FILE_URL varchar(500) DEFAULT NULL,"
Write-Host "  ADD COLUMN MOA_PUBLIC_ID varchar(255) DEFAULT NULL,"
Write-Host "  ADD COLUMN MOA_START_DATE date DEFAULT NULL,"
Write-Host "  ADD COLUMN MOA_END_DATE date DEFAULT NULL,"
Write-Host "  ADD COLUMN MOA_UPLOAD_DATE timestamp NULL DEFAULT NULL;"
Write-Host ""

Write-Host "STEP 6: Update Render Environment Variables" -ForegroundColor Cyan
Write-Host "In your Render dashboard, update these environment variables:"
Write-Host "  DB_HOST=[RAILWAY_MYSQL_HOST]"
Write-Host "  DB_USERNAME=[RAILWAY_MYSQL_USER]"
Write-Host "  DB_PASSWORD=[RAILWAY_MYSQL_PASSWORD]" 
Write-Host "  DB_NAME=[RAILWAY_MYSQL_DATABASE]"
Write-Host ""

Write-Host "🎯 NEXT STEPS:" -ForegroundColor Yellow
Write-Host "1. Complete Railway MySQL deployment"
Write-Host "2. Get credentials from Railway dashboard"
Write-Host "3. Run: php test_railway_connection.php (I'll create this)"
Write-Host "4. Import backup data"
Write-Host "5. Update Render environment variables"
Write-Host "6. Test your application"
Write-Host ""

Write-Host "✅ Files ready for Railway migration:" -ForegroundColor Green
Write-Host "• .env.railway - Configuration template"
Write-Host "• database/sql3806785.sql - Your backup data"
Write-Host "• database_restoration.sql - MOA column additions"