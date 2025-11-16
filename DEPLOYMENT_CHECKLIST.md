# Flask + PHP Deployment Checklist

## ✅ Files Updated:

### 1. **Deployment Configuration**
- `Procfile` - Updated to use start.sh
- `start.sh` - Starts both Flask (port 5000) and PHP with proper cleanup
- `flask_app.py` - Backup entry point (Pylance error resolved)
- `requirements.txt` - Python dependencies
- `runtime.txt` - Python version

### 2. **API Proxies Created**
- `api/predict.php` - Proxy for /predict endpoint (POST)
- `api/post_analysis.php` - Proxy for /post_analysis endpoint (GET)

### 3. **Flask App Updated**
- `ML/sample_frontend/app.py` - Uses environment variables for database
- Database connection uses same credentials as PHP

### 4. **JavaScript Updated**
- `js/mainDashboard.js` - Uses environment-aware API URLs
  - Local: Direct to Flask (localhost:5000)
  - Production: Uses PHP proxy (api/predict.php)

### 5. **PHP Backend Updated**
- `ajaxhandler/postAssessmentAveragesAjax.php` - Calls Flask internally

## 🚀 Deployment Steps:

1. **Commit & Push:**
   ```bash
   git add .
   git commit -m "Configure Flask + PHP deployment for Render"
   git push origin feature-updates
   ```

2. **Render Environment Variables:**
   Set these in your Render dashboard:
   ```
   DB_HOST=sql102.infinityfree.com:3306
   DB_USERNAME=if0_40429035
   DB_PASSWORD=bRXtz7w8GIW8X
   DB_NAME=if0_40429035_internconnect
   ```

3. **Deploy:**
   - Render will automatically deploy using the updated Procfile
   - Both Flask API and PHP app will run together

## 🔗 How it Works:

- **Production URL:** `https://your-app.onrender.com`
- **Flask API:** Internal on port 5000 (not directly accessible)
- **API Calls:** Go through PHP proxies (`api/predict.php`, `api/post_analysis.php`)
- **Database:** Both Flask and PHP use the same InfinityFree MySQL database

## ✅ Ready to Deploy!