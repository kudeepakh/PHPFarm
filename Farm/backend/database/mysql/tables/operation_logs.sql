-- Operation Logs Table for Correlation ID Tracking
-- This table stores correlation tracking for all stored procedure operations

CREATE TABLE IF NOT EXISTS operation_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    correlation_id VARCHAR(36) NOT NULL,
    transaction_id VARCHAR(36) DEFAULT NULL,
    request_id VARCHAR(36) DEFAULT NULL,
    operation VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    execution_time_ms INT DEFAULT NULL,
    status ENUM('started', 'completed', 'failed') DEFAULT 'started',
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for efficient querying
    INDEX idx_correlation_id (correlation_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_request_id (request_id),
    INDEX idx_operation (operation),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create stored procedure for logging operations
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_log_operation$$
CREATE PROCEDURE sp_log_operation(
    IN p_correlation_id VARCHAR(36),
    IN p_transaction_id VARCHAR(36),
    IN p_request_id VARCHAR(36),
    IN p_operation VARCHAR(100),
    IN p_details TEXT,
    IN p_status ENUM('started', 'completed', 'failed'),
    IN p_execution_time_ms INT DEFAULT NULL,
    IN p_error_message TEXT DEFAULT NULL
)
BEGIN
    INSERT INTO operation_logs (
        correlation_id, 
        transaction_id, 
        request_id, 
        operation, 
        details, 
        status, 
        execution_time_ms, 
        error_message
    ) VALUES (
        p_correlation_id, 
        p_transaction_id, 
        p_request_id, 
        p_operation, 
        p_details, 
        COALESCE(p_status, 'started'), 
        p_execution_time_ms, 
        p_error_message
    );
END$$

-- Cleanup old operation logs (older than 30 days)
DROP PROCEDURE IF EXISTS sp_cleanup_operation_logs$$
CREATE PROCEDURE sp_cleanup_operation_logs()
BEGIN
    DELETE FROM operation_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    SELECT ROW_COUNT() AS deleted_count, 'Operation logs cleaned up' AS message;
END$$

DELIMITER ;