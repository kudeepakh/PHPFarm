<?php

namespace PHPFrarm\Core\Exceptions;

/**
 * HTTP Exception Classes for proper error handling
 */

class HttpException extends \Exception
{
    protected int $statusCode;
    
    public function __construct(string $message = "", int $statusCode = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

class BadRequestHttpException extends HttpException
{
    public function __construct(string $message = "Bad Request", \Throwable $previous = null)
    {
        parent::__construct($message, 400, $previous);
    }
}

class UnauthorizedHttpException extends HttpException
{
    public function __construct(string $message = "Unauthorized", \Throwable $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}

class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = "Forbidden", \Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}

class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = "Not Found", \Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}

class ConflictHttpException extends HttpException
{
    public function __construct(string $message = "Conflict", \Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}

class TooManyRequestsHttpException extends HttpException
{
    public function __construct(string $message = "Too Many Requests", \Throwable $previous = null)
    {
        parent::__construct($message, 429, $previous);
    }
}