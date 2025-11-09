# Flask API Deployment Guide

## Step 1: Create Separate Flask API Repository

Create a new repository `InternConnect-API` with this structure:

```
InternConnect-API/
├── app.py                 # Copy from ML/sample_frontend/app.py (already updated)
├── requirements.txt       # Python dependencies (already created)
├── Procfile              # web: gunicorn app:app
├── runtime.txt           # python-3.11.5
└── model/                # Copy entire ML/model/ folder
    └── pre-assessment.joblib
```

## Step 2: Deploy Flask API on Render

1. **Create New Web Service**:
   - Go to Render Dashboard
   - Click **New +** → **Web Service**
   - Connect your `InternConnect-API` repository

2. **Configure Service**:
   - **Name**: `internconnect-api` (or your preferred name)
   - **Environment**: `Python 3`
   - **Build Command**: `pip install -r requirements.txt`
   - **Start Command**: `gunicorn app:app`

3. **Set Environment Variables**:
   ```
   DB_HOST=sql3.freesqldatabase.com:3306
   DB_USERNAME=sql3806785
   DB_PASSWORD=DAl9FGjxvF
   DB_NAME=sql3806785
   ```

4. **Deploy**: Your API will be available at:
   `https://internconnect-api.onrender.com`

## Step 3: Update Main App

In your main InternConnect repository, update the API URL in:
- `js/mainDashboard.js` (already updated)
- Any other files that call the Flask API

Replace `your-flask-api-name` with your actual Render service name.

## Step 4: Test

1. Deploy both services
2. Test prediction functionality on your live website
3. Check Render logs for any issues

## Files Already Prepared:

✅ `requirements.txt` - Python dependencies
✅ `ML/sample_frontend/app.py` - Updated with environment variables
✅ `js/mainDashboard.js` - Updated with environment-aware API URL