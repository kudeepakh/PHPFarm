<?php

namespace Farm\Backend\Tests\Integration;

use Farm\Backend\Tests\TestCase;
use GuzzleHttp\Client as HttpClient;
use MongoDB\Client as MongoClient;
use MongoDB\BSON\UTCDateTime;

class LoggingIntegrationTest extends TestCase
{
    private function enableMongoLogging(): void
    {
        $_ENV['LOG_TO_MONGO'] = 'true';
        putenv('LOG_TO_MONGO=true');

        $_ENV['MONGO_HOST'] = $_ENV['MONGO_HOST'] ?? 'mongodb';
        $_ENV['MONGO_PORT'] = '27017';
        putenv('MONGO_PORT=27017');

        $this->resetLoggerStaticState();
    }

    private function resetLoggerStaticState(): void
    {
        $logger = new \ReflectionClass(\PHPFrarm\Core\Logger::class);
        foreach (['db', 'mongoAvailable', 'buffer'] as $property) {
            if ($logger->hasProperty($property)) {
                $prop = $logger->getProperty($property);
                $prop->setAccessible(true);

                if ($property === 'mongoAvailable') {
                    $prop->setValue(null, true);
                } elseif ($property === 'buffer') {
                    $prop->setValue(null, []);
                } else {
                    $prop->setValue(null, null);
                }
            }
        }
    }

    private function getMongoClient(): MongoClient
    {
        $host = $_ENV['MONGO_HOST'] ?? getenv('MONGO_HOST') ?: 'mongodb';
        $port = $_ENV['MONGO_PORT'] ?? getenv('MONGO_PORT') ?: '27017';
        $username = $_ENV['MONGO_ROOT_USER'] ?? getenv('MONGO_ROOT_USER') ?: 'admin';
        $password = $_ENV['MONGO_ROOT_PASSWORD'] ?? getenv('MONGO_ROOT_PASSWORD') ?: 'mongo_password_change_me';

        if ($username !== '' && $password !== '') {
            $uri = "mongodb://{$username}:{$password}@{$host}:{$port}/?authSource=admin";
        } else {
            $uri = "mongodb://{$host}:{$port}";
        }

        return new MongoClient($uri);
    }

    private function generateUlid(): string
    {
        return '01HQZK' . strtoupper(bin2hex(random_bytes(10)));
    }

    /**
     * @test
     */
    public function it_logs_api_status_request_to_mongodb()
    {
        $this->enableMongoLogging();

        $client = new HttpClient([
            'base_uri' => 'http://localhost',
            'http_errors' => false,
            'timeout' => 10
        ]);

        $startTime = new UTCDateTime((int) (microtime(true) * 1000) - 1000);

        $response = $client->get('/api/status', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Correlation-Id' => $this->generateUlid(),
                'X-Transaction-Id' => $this->generateUlid(),
                'X-Request-Id' => $this->generateUlid()
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Correlation-Id'));
        $this->assertTrue($response->hasHeader('X-Transaction-Id'));
        $this->assertTrue($response->hasHeader('X-Request-Id'));

        $mongo = $this->getMongoClient();
        $dbName = $_ENV['MONGO_DATABASE'] ?? 'phpfrarm_logs';
        $collection = $mongo->selectDatabase($dbName)->selectCollection('access_logs');

        $logEntry = $collection->findOne([
            'timestamp' => ['$gte' => $startTime],
            'context.path' => '/api/status',
            'context.method' => 'GET'
        ]);

        $this->assertNotNull($logEntry, 'Expected access log entry for /api/status in MongoDB.');
    }
}
