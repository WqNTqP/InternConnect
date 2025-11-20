-- SQL script to prepare for Forgot Password functionality
-- This will create the password_reset_tokens table if it doesn't exist

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'admin') NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user (user_id, user_type),
    INDEX idx_expires (expires_at)
);

-- Sample data for testing (optional - remove after testing)
-- Make sure to have at least one student and one admin with valid emails

-- Example: Update a test student with a valid email (students use interns_details table)
-- UPDATE interns_details SET EMAIL = 'student@test.com' WHERE INTERNS_ID = '2';

-- Example: Update a test admin with a valid email (admins use coordinator table)
-- UPDATE coordinator SET EMAIL = 'admin@test.com' WHERE COORDINATOR_ID = '123456';

-- Note: The system supports different roles in coordinator table:
-- ROLE can be: 'ADMIN', 'COORDINATOR', 'SUPERADMIN'
-- All use the same forgot password flow but get customized emails

-- Clean up expired tokens (run this periodically)
-- DELETE FROM password_reset_tokens WHERE expires_at < NOW();