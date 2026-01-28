<?php

namespace Farm\Backend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPFrarm\Core\Router;

class RouterDecodeBodyTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
        $_FILES = [];
        parent::tearDown();
    }

    public function testParsesFormUrlEncodedBody(): void
    {
        $method = new \ReflectionMethod(Router::class, 'decodeBody');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'email=test%40example.com&otp=123456', 'application/x-www-form-urlencoded', 32);

        $this->assertIsArray($result);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('123456', $result['otp']);
    }

    public function testParsesMultipartBodyWithFiles(): void
    {
        $_POST = ['field1' => 'value1'];
        $_FILES = [
            'upload' => [
                'name' => 'file.txt',
                'type' => 'text/plain',
                'size' => 12,
                'tmp_name' => '/tmp/php123',
                'error' => UPLOAD_ERR_OK,
            ]
        ];

        $method = new \ReflectionMethod(Router::class, 'decodeBody');
        $method->setAccessible(true);

        $result = $method->invoke(null, '', 'multipart/form-data; boundary=----test', 12);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('fields', $result);
        $this->assertArrayHasKey('files', $result);
        $this->assertSame('value1', $result['fields']['field1']);
        $this->assertSame('file.txt', $result['files']['upload']['name']);
    }
}
