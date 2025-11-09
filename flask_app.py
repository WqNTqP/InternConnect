#!/usr/bin/env python3
"""
Flask API entry point for Render deployment
This is a backup entry point - the main deployment uses start.sh
"""
import sys
import os
import subprocess

def main():
    """Start the Flask app from the ML/sample_frontend directory"""
    print("Starting Flask API using direct execution...")
    
    # Change to the Flask app directory
    flask_dir = os.path.join(os.path.dirname(__file__), 'ML', 'sample_frontend')
    
    if not os.path.exists(flask_dir):
        print(f"Error: Flask directory not found at {flask_dir}")
        sys.exit(1)
    
    # Set environment variables
    env = os.environ.copy()
    env['FLASK_ENV'] = 'production'
    env['PORT'] = str(int(os.environ.get('PORT', 5000)))
    
    # Run the Flask app
    os.chdir(flask_dir)
    subprocess.run([sys.executable, 'app.py'], env=env)

if __name__ == "__main__":
    main()