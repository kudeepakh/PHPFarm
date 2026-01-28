-- Idempotent Requests Table
-- Stores idempotency keys to prevent duplicate processing of requests
-- Automatically expires old records

DROP TABLE IF EXISTS idempotent_requests;

CREATE TABLE idempotent_requests (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    request_hash CHAR(64) NOT NULL, -- SHA-256 hash of normalized request
    response_body LONGTEXT NULL,
    response_headers JSON NULL,
    status_code INT NOT NULL DEFAULT 200,
    correlation_id CHAR(36) NULL,
    transaction_id CHAR(36) NULL,
    request_id CHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    INDEX idx_idempotency_key (idempotency_key),
    INDEX idx_expires_at (expires_at),
    INDEX idx_correlation_id (correlation_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stored procedure to store idempotent request
DROP PROCEDURE IF EXISTS sp_store_idempotent_request$$

CREATE PROCEDURE sp_store_idempotent_request(
    IN p_idempotency_key VARCHAR(255),
    IN p_request_hash CHAR(64),
    IN p_response_body LONGTEXT,
    IN p_response_headers JSON,
    IN p_status_code INT,
    IN p_correlation_id CHAR(36),
    IN p_transaction_id CHAR(36),
    IN p_request_id CHAR(36),
    IN p_ttl_hours INT DEFAULT 24
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO idempotent_requests (
        idempotency_key,
        request_hash,
        response_body,
        response_headers,
        status_code,
        correlation_id,
        transaction_id,
        request_id,
        expires_at
    ) VALUES (
        p_idempotency_key,
        p_request_hash,
        p_response_body,
        p_response_headers,
        p_status_code,
        p_correlation_id,
        p_transaction_id,
        p_request_id,
        DATE_ADD(NOW(), INTERVAL p_ttl_hours HOUR)
    )
    ON DUPLICATE KEY UPDATE
        response_body = VALUES(response_body),
        response_headers = VALUES(response_headers),
        status_code = VALUES(status_code),
        expires_at = DATE_ADD(NOW(), INTERVAL p_ttl_hours HOUR);
    
    COMMIT;
    
    SELECT 'success' as status, 'Idempotent request stored' as message;
END$$

-- Stored procedure to get idempotent request
DROP PROCEDURE IF EXISTS sp_get_idempotent_request$$

CREATE PROCEDURE sp_get_idempotent_request(
    IN p_idempotency_key VARCHAR(255),
    IN p_request_hash CHAR(64)
)
BEGIN
    SELECT 
        id,
        idempotency_key,
        request_hash,
        response_body,
        response_headers,
        status_code,
        correlation_id,
        transaction_id,
        request_id,
        created_at,
        expires_at,
        CASE 
            WHEN expires_at IS NULL OR expires_at > NOW() THEN 'valid'
            ELSE 'expired'
        END as status
    FROM idempotent_requests
    WHERE idempotency_key = p_idempotency_key
      AND (expires_at IS NULL OR expires_at > NOW())
    LIMIT 1;
END$$

-- Stored procedure to cleanup expired requests
DROP PROCEDURE IF EXISTS sp_cleanup_expired_idempotent_requests$$

CREATE PROCEDURE sp_cleanup_expired_idempotent_requests()
BEGIN
    DECLARE deleted_count INT DEFAULT 0;
    
    DELETE FROM idempotent_requests 
    WHERE expires_at IS NOT NULL 
      AND expires_at < NOW();
    
    SET deleted_count = ROW_COUNT();
    
    SELECT 
        'success' as status,
        deleted_count as deleted_records,
        'Expired idempotent requests cleaned up' as message;
END$$

DELIMITER ;