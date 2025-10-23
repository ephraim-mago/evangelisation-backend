<?php

namespace Framework\Http\Exception;

use Throwable;
use RuntimeException;

class HttpException extends RuntimeException
{
    /**
     * The HTTP status code.
     * 
     * @var int
     */
    protected $statusCode;

    /**
     * The HTTP headers.
     * 
     * @var array
     */
    protected $headers;

    /**
     * Create a new HTTP exception instance.
     * 
     * @param  int  $statusCode
     * @param  string  $message
     * @param  \Throwable|null  $previous
     * @param  array  $headers
     * @param  int  $code
     * @return void
     */
    public function __construct(
        int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0,
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP status code.
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the HTTP headers.
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set the HTTP headers.
     * 
     * @param  array  $headers
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }
}
