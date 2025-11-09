#!/bin/bash

echo "Installing Python dependencies..."
pip install -r requirements.txt

echo "Starting Flask API on port 5000..."
cd ML/sample_frontend
export FLASK_ENV=production
python app.py &
FLASK_PID=$!
cd ../..

echo "Starting PHP server on port $PORT..."
php -S 0.0.0.0:${PORT:-10000} -t . &
PHP_PID=$!

echo "Both services started:"
echo "- Flask API: Internal on port 5000"
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