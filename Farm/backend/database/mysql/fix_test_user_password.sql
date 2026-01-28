-- Fix password for test@example.com
UPDATE users 
SET password_hash = '$2y$10$sFmTWkw1gkskQdKHuATehOHGv6vXMLMD1j9A2128J0k08lJr0V79O'
WHERE email = 'test@example.com';

-- Verify update
SELECT id, email, LEFT(password_hash, 30) as hash_preview, status, account_status 
FROM users 
WHERE email = 'test@example.com';
