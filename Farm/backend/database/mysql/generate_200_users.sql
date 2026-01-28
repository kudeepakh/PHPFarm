DELIMITER $$

DROP PROCEDURE IF EXISTS generate_test_users$$

CREATE PROCEDURE generate_test_users()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE user_id VARCHAR(36);
    DECLARE user_email VARCHAR(255);
    DECLARE user_phone VARCHAR(20);
    DECLARE user_first_name VARCHAR(100);
    DECLARE user_last_name VARCHAR(100);
    DECLARE rand_status VARCHAR(20);
    DECLARE rand_role_id VARCHAR(36);
    
    -- Default password hash for 'password123'
    DECLARE default_password VARCHAR(255) DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    
    WHILE i <= 200 DO
        -- Generate unique user ID
        SET user_id = MD5(CONCAT('user_', i, '_', UNIX_TIMESTAMP(), RAND()));
        
        -- Generate email with timestamp for uniqueness
        SET user_email = CONCAT('user', i, '_', UNIX_TIMESTAMP(), '@example.com');
        
        -- Generate phone (US format)
        SET user_phone = CONCAT('+1555', LPAD(FLOOR(1000000 + RAND() * 8999999), 7, '0'));
        
        -- Generate first names (variety)
        SET user_first_name = ELT(
            FLOOR(1 + RAND() * 20),
            'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa',
            'James', 'Mary', 'William', 'Patricia', 'Richard', 'Jennifer', 'Thomas',
            'Linda', 'Charles', 'Barbara', 'Daniel', 'Elizabeth'
        );
        
        -- Generate last names (variety)
        SET user_last_name = ELT(
            FLOOR(1 + RAND() * 20),
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
            'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
            'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'
        );
        
        -- Random status (90% active, 10% inactive)
        SET rand_status = IF(RAND() < 0.9, 'active', 'inactive');
        
        -- Insert user into users table
        INSERT INTO users (
            id, 
            email, 
            phone, 
            password_hash,
            first_name, 
            last_name, 
            status,
            account_status,
            email_verified,
            phone_verified,
            last_login_at,
            created_at,
            updated_at,
            token_version
        ) VALUES (
            user_id,
            user_email,
            user_phone,
            default_password,
            user_first_name,
            user_last_name,
            rand_status,
            IF(rand_status = 'active', 'active', 'pending_verification'), -- Valid account_status enum
            FLOOR(RAND() * 2), -- Random email_verified (0 or 1)
            FLOOR(RAND() * 2), -- Random phone_verified (0 or 1)
            DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY), -- Random last login in last 30 days
            DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 365) DAY), -- Random created_at in last year
            NOW(),
            0
        );
        
        -- Insert email into user_identifiers
        INSERT INTO user_identifiers (
            identifier_id,
            user_id,
            identifier_type,
            identifier_value,
            is_primary,
            is_verified,
            verified_at,
            created_at,
            updated_at
        ) VALUES (
            MD5(CONCAT('email_', user_id, RAND())),
            user_id,
            'email',
            user_email,
            1,
            FLOOR(RAND() * 2),
            IF(RAND() < 0.5, NOW(), NULL),
            NOW(),
            NOW()
        );
        
        -- Insert phone into user_identifiers
        INSERT INTO user_identifiers (
            identifier_id,
            user_id,
            identifier_type,
            identifier_value,
            is_primary,
            is_verified,
            verified_at,
            created_at,
            updated_at
        ) VALUES (
            MD5(CONCAT('phone_', user_id, RAND())),
            user_id,
            'phone',
            user_phone,
            0,
            FLOOR(RAND() * 2),
            IF(RAND() < 0.5, NOW(), NULL),
            NOW(),
            NOW()
        );
        
        -- Assign random role (if roles exist)
        -- 60% get 'admin' role, 30% get no role, 10% get 'superadmin'
        IF RAND() < 0.6 THEN
            SET rand_role_id = '01000000-0000-7000-8000-000000000002'; -- admin role
            INSERT INTO user_roles (user_role_id, user_id, role_id, assigned_at)
            VALUES (MD5(CONCAT('user_role_', user_id, RAND())), user_id, rand_role_id, NOW());
        ELSEIF RAND() < 0.1 THEN
            SET rand_role_id = '01000000-0000-7000-8000-000000000001'; -- superadmin role
            INSERT INTO user_roles (user_role_id, user_id, role_id, assigned_at)
            VALUES (MD5(CONCAT('user_role_', user_id, RAND())), user_id, rand_role_id, NOW());
        END IF;
        
        SET i = i + 1;
    END WHILE;
    
    SELECT CONCAT('Successfully created 200 test users') AS result;
END$$

DELIMITER ;

-- Execute the procedure
CALL generate_test_users();

-- Drop the procedure after use
DROP PROCEDURE IF EXISTS generate_test_users;
