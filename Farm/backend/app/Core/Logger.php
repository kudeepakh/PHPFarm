<?php

namespace PHPFrarm\Core;

use MongoDB\Client;
use MongoDB\Database as MongoDatabase;
use MongoDB\Collection;

/**
 * MongoDB Logger - For observability, audit trails, and metrics
 * 
 * MANDATORY: All API operations must log to MongoDB with correlation IDs
 * Falls back to file logging if MongoDB is unavailable
 */
class Logger
{
    private static ?MongoDatabase $db = null;
    private static bool $mongoAvailable = true;
    private static array $buffer = [];
    private static int $bufferSize = 100;
    private static string $logPath = '/var/www/html/logs/';

    /**
     * Initialize MongoDB connection
     */
    private static function getDatabase(): ?MongoDatabase
    {
        if (($_ENV['LOG_TO_MONGO'] ?? 'true') !== 'true') {
            return null;
        }

        if (!self::$mongoAvailable) {
            return null;
        }

        if (self::$db === null) {
            try {
                $host = $_ENV['MONGO_HOST'] ?? 'mongodb';
                $port = $_ENV['MONGO_PORT'] ?? '27017';
                $username = $_ENV['MONGO_ROOT_USER'] ?? 'admin';
                $password = $_ENV['MONGO_ROOT_PASSWORD'] ?? '';
                $database = $_ENV['MONGO_DATABASE'] ?? 'phpfrarm_logs';

                // Build connection URI with authentication
                if (!empty($username) && !empty($password)) {
                    $uri = "mongodb://{$username}:{$password}@{$host}:{$port}/?authSource=admin";
                } else {
                    $uri = "mongodb://{$host}:{$port}";
                }

                $client = new Client($uri, [
                    'connectTimeoutMS' => 5000,
                    'serverSelectionTimeoutMS' => 5000
                ]);
                self::$db = $client->selectDatabase($database);

                // Test connection
                self::$db->command(['ping' => 1]);

                // Create indexes on first connection
                self::ensureIndexes();
            } catch (\Exception $e) {
                self::$mongoAvailable = false;
                error_log('MongoDB connection failed: ' . $e->getMessage());
                return null;
            }
        }

        return self::$db;
    }

    /**
     * Ensure required indexes exist
     */
    private static function ensureIndexes(): void
    {
        if (self::$db === null) {
            return;
        }

        try {
            $collections = ['application_logs', 'access_logs', 'audit_logs', 'security_logs'];
            
            foreach ($collections as $collectionName) {
                $collection = self::$db->selectCollection($collectionName);
                
                // Create indexes
                $collection->createIndex(['correlation_id' => 1]);
                $collection->createIndex(['transaction_id' => 1]);
                $collection->createIndex(['request_id' => 1]);
                $collection->createIndex(['timestamp' => -1]);
                $collection->createIndex(['level' => 1]);
            }
        } catch (\Exception $e) {
            error_log('Failed to create MongoDB indexes: ' . $e->getMessage());
        }
    }

    /**
     * Log to specific collection with context
     */
    private static function log(string $collection, string $level, string $message, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'correlation_id' => TraceContext::getCorrelationId(),
            'transaction_id' => TraceContext::getTransactionId(),
            'request_id' => TraceContext::getRequestId(),
            'context' => $context,
            'server' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
            ]
        ];

        // Mask PII in logs
        $logEntry = self::maskPII($logEntry);
        $maskedContext = $logEntry['context'] ?? [];

        // Try MongoDB first
        $db = self::getDatabase();
        if ($db !== null) {
            try {
                $mongoEntry = $logEntry;
                $mongoEntry['timestamp'] = new \MongoDB\BSON\UTCDateTime();
                $db->selectCollection($collection)->insertOne($mongoEntry);
            } catch (\Exception $e) {
                error_log('Failed to write to MongoDB: ' . $e->getMessage());
            }
        }

        // Also log to file (always in development, or if MongoDB failed)
        if (($_ENV['LOG_TO_FILE'] ?? 'true') === 'true' || $db === null) {
            self::logToFile($level, $message, $maskedContext);
        }
    }

    /**
     * Application logs
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('application_logs', 'INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('application_logs', 'WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('application_logs', 'ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (($_ENV['LOG_LEVEL'] ?? 'info') === 'debug') {
            self::log('application_logs', 'DEBUG', $message, $context);
        }
    }

    /**
     * Access logs
     */
    public static function access(string $message, array $context = []): void
    {
        self::log('access_logs', 'ACCESS', $message, $context);
    }

    /**
     * Audit logs
     */
    public static function audit(string $action, array $context = []): void
    {
        self::log('audit_logs', 'AUDIT', $action, $context);
    }

    /**
     * Security logs
     */
    public static function security(string $message, array $context = []): void
    {
        self::log('security_logs', 'SECURITY', $message, $context);
    }

    /**
     * Mask PII data
     */
    private static function maskPII(array $data): array
    {
        $piiFields = ['password', 'token', 'secret', 'credit_card', 'ssn', 'api_key'];
        
        array_walk_recursive($data, function (&$value, $key) use ($piiFields) {
            if (in_array(strtolower($key), $piiFields)) {
                $value = '***MASKED***';
            }
        });

        return $data;
    }

    /**
     * Log to file as backup
     */
    private static function logToFile(string $level, string $message, array $context): void
    {
        $logDir = __DIR__ . '/../../logs';
        $logFile = $logDir . '/' . date('Y-m-d') . '.log';
        
        if (!is_dir($logDir)) {
            if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                return;
            }
        }

        if (!is_writable($logDir)) {
            return;
        }

        $logLine = sprintf(
            "[%s] [%s] %s | CorrelationID: %s | Context: %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            TraceContext::getCorrelationId(),
            json_encode($context)
        );

        @file_put_contents($logFile, $logLine, FILE_APPEND);
    }
}
