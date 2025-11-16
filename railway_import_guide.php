<?php
/**
 * Railway Database Import Helper
 * Helps import SQL file to Railway MySQL database
 */

echo "=== RAILWAY DATABASE IMPORT GUIDE ===\n\n";

echo "STEP 1: Get Railway Credentials\n";
echo "===============================\n";
echo "In Railway dashboard → MySQL service → Variables tab\n";
echo "Copy these 5 values:\n";
echo "• MYSQL_HOST (e.g., containers-us-west-123.railway.app)\n";
echo "• MYSQL_PORT (usually 3306)\n";
echo "• MYSQL_USER (usually root)\n"; 
echo "• MYSQL_PASSWORD (long random string)\n";
echo "• MYSQL_DATABASE (usually railway)\n\n";

echo "STEP 2: Import via Command Line\n";
echo "===============================\n";
echo "Replace [CREDENTIALS] with your actual Railway values:\n\n";

echo "Windows (PowerShell):\n";
echo 'mysql -h [MYSQL_HOST] -P [MYSQL_PORT] -u [MYSQL_USER] -p[MYSQL_PASSWORD] [MYSQL_DATABASE] < "database\\sql3806785.sql"' . "\n\n";

echo "Example with real credentials:\n";
echo 'mysql -h containers-us-west-123.railway.app -P 3306 -u root -pAbc123XyZ789 railway < "database\\sql3806785.sql"' . "\n\n";

echo "STEP 3: Alternative - PHP Import Script\n";
echo "=======================================\n";
echo "If MySQL command line doesn't work, I can create a PHP script\n";
echo "that reads your SQL file and executes it line by line.\n\n";

echo "STEP 4: Verify Import\n";
echo "====================\n";
echo "After import, check tables with:\n";
echo "mysql -h [MYSQL_HOST] -P [MYSQL_PORT] -u [MYSQL_USER] -p[MYSQL_PASSWORD] [MYSQL_DATABASE] -e \"SHOW TABLES;\"\n\n";

echo "TROUBLESHOOTING:\n";
echo "===============\n";
echo "• If 'mysql command not found': Install MySQL client or use PHP script\n";
echo "• If 'Access denied': Check credentials are correct\n";
echo "• If 'Connection refused': Check host and port\n\n";

echo "Ready? Get your Railway credentials from Variables tab first!\n";
?>
