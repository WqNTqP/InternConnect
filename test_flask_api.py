#!/usr/bin/env python3
"""
Test script for the Flask API endpoints
"""
import requests
import json

# Test data for prediction
test_data = {
    "GE 2": 85,
    "GE 3": 88,
    "GE 4": 90,
    "GE 5": 87,
    "GE 6": 89,
    "GE 7": 91,
    "GE 8": 86,
    "PE 1": 88,
    "PE 2": 89,
    "PE 3": 87,
    "NSTP 1": 90,
    "NSTP 2": 91,
    "IT 111": 85,
    "IT 112": 88,
    "IT 211": 89,
    "IT 212": 87,
    "IT 213": 90,
    "IT 221": 86,
    "IT 311": 88,
    "IT 312": 89,
    "CAP 101": 87,
    "CAP 102": 90,
    "SP 101": 88
}

def test_predict_endpoint():
    """Test the /predict endpoint"""
    print("Testing /predict endpoint...")
    try:
        response = requests.post('http://127.0.0.1:5000/predict', 
                               json=test_data,
                               headers={'Content-Type': 'application/json'})
        
        if response.status_code == 200:
            result = response.json()
            print("✅ Prediction successful!")
            print(f"Predicted OJT Placement: {result.get('prediction', 'N/A')}")
            print(f"Confidence: {result.get('confidence', 'N/A')}")
        else:
            print(f"❌ Error: {response.status_code}")
            print(response.text)
    except Exception as e:
        print(f"❌ Connection error: {e}")

def test_post_analysis_endpoint():
    """Test the /post_analysis endpoint"""
    print("\nTesting /post_analysis endpoint...")
    try:
        # Sample post-assessment data
        post_data = {
            "student_id": "12345678",
            "technical_skills": 85,
            "problem_solving": 88,
            "communication": 90,
            "teamwork": 87,
            "adaptability": 89
        }
        
        response = requests.post('http://127.0.0.1:5000/post_analysis', 
                               json=post_data,
                               headers={'Content-Type': 'application/json'})
        
        if response.status_code == 200:
            result = response.json()
            print("✅ Post-analysis successful!")
            print(f"Analysis result: {result}")
        else:
            print(f"❌ Error: {response.status_code}")
            print(response.text)
    except Exception as e:
        print(f"❌ Connection error: {e}")

def test_health_check():
    """Test basic connectivity"""
    print("Testing Flask app connectivity...")
    try:
        response = requests.get('http://127.0.0.1:5000/')
        print(f"Status: {response.status_code}")
        if response.status_code == 404:
            print("✅ Flask is running (404 expected for root path)")
        else:
            print(f"Response: {response.text[:100]}")
    except Exception as e:
        print(f"❌ Cannot connect to Flask app: {e}")

if __name__ == "__main__":
    print("=== Flask API Test Suite ===")
    test_health_check()
    test_predict_endpoint()
    test_post_analysis_endpoint()
    print("\n=== Test Complete ===")