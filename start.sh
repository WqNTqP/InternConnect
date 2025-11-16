#!/bin/bash
set -e  # Exit on any error

echo "🚀 InternConnect Startup Script"
echo "=============================="

echo "📦 Installing Python dependencies..."
pip install -r requirements.txt
echo "✅ Python dependencies installed"

echo "🐍 Starting Flask API on port 5000..."
cd ML/sample_frontend

# Test Python and Flask imports first
echo "🔍 Testing Python and dependencies..."
python3 --version
python3 -c "import flask; print('Flask import: OK')" || { echo "❌ Flask import failed"; exit 1; }
python3 -c "import pandas; print('Pandas import: OK')" || { echo "❌ Pandas import failed"; exit 1; }
python3 -c "import sklearn; print('Scikit-learn import: OK')" || { echo "❌ Scikit-learn import failed"; exit 1; }
echo "✅ All dependencies OK"

# Set environment variables
export FLASK_ENV=production
export PYTHONUNBUFFERED=1

# Set database environment variables if not set
export DB_HOST=${DB_HOST:-mainline.proxy.rlwy.net:31782}
export DB_USERNAME=${DB_USERNAME:-sql3806785}
export DB_PASSWORD=${DB_PASSWORD:-DAl9FGjxvF}
export DB_NAME=${DB_NAME:-sql3806785}

echo "🔧 Environment variables:"
echo "   DB_HOST: $DB_HOST"
echo "   DB_USERNAME: $DB_USERNAME"
echo "   DB_NAME: $DB_NAME"

# Start Flask with Gunicorn (production WSGI server)
echo "🚀 Launching Flask application with Gunicorn..."
gunicorn --bind 0.0.0.0:5000 --workers 2 --timeout 60 app:app &
FLASK_PID=$!
echo "✅ Flask API started with Gunicorn PID: $FLASK_PID"

# Check if Flask process is still running
sleep 2
if kill -0 $FLASK_PID 2>/dev/null; then
    echo "✅ Flask process is running"
else
    echo "❌ Flask process died immediately"
    exit 1
fi

cd ../..

echo "🌐 Starting PHP server on port $PORT..."
php -S 0.0.0.0:${PORT:-10000} -t . &
PHP_PID=$!
echo "✅ PHP server started with PID: $PHP_PID"

echo "Both services started:"
echo "- Flask API: Production Gunicorn server on port 5000"
echo "- PHP App: http://localhost:${PORT:-10000}"

# Function to handle shutdown
cleanup() {
    echo "Shutting down services..."
    kill $FLASK_PID 2>/dev/null
    kill $PHP_PID 2>/dev/null
    exit
}

# Set up signal handlers
trap cleanup SIGTERM SIGINT

# Wait for both processes
wait $FLASK_PID $PHP_PID