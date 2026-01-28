<?php

namespace PHPFrarm\Core;

/**
 * BaseController
 *
 * Lightweight base controller used by legacy modules.
 * Provides request parsing and response helpers.
 */
abstract class BaseController
{
    protected array $request = [];

    public function __construct()
    {
        $this->request = [
            'path_params' => [],
            'query_params' => $_GET,
            'body' => $this->getBody(),
            'headers' => getallheaders() ?: [],
            'user' => [],
        ];
    }

    protected function sendSuccess(array $data, string $message = 'success', int $statusCode = 200): void
    {
        Response::success($data, $message, $statusCode);
    }

    protected function sendError(string $message, int $statusCode = 500, string $errorCode = ''): void
    {
        Response::error($message, $statusCode, $errorCode ?: null);
    }

    private function getBody(): array
    {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || $rawBody === '') {
            return $_POST ?? [];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST ?? [];
    }
}
