@echo off
echo Installing Python dependencies...
pip install -r requirements.txt

echo Starting Flask API on port 5000...
cd ML\sample_frontend
start /B python app.py
cd ..\..

echo Starting PHP server on port 8000...
start /B php -S localhost:8000 -t .

echo Both services started:
echo - Flask API: http://localhost:5000
echo - PHP App: http://localhost:8000

echo Press any key to stop both services...
pause

echo Stopping services...
taskkill /f /im python.exe 2>nul
taskkill /f /im php.exe 2>nul