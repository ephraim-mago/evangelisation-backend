<?php

namespace Framework\Http\Exception;

use Throwable;
use RuntimeException;
use Psr\Http\Message\ResponseInterface;

class HttpResponseException extends RuntimeException
{
    /**
     * The underlying response instance.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * Create a new HTTP response exception instance.
     *
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @param  \Throwable|null  $previous
     */
    public function __construct(ResponseInterface $response, ?Throwable $previous = null)
    {
        parent::__construct(
            $previous?->getMessage() ?? '',
            $previous?->getCode() ?? 0,
            $previous
        );

        $this->response = $response;
    }

    /**
     * Get the underlying response instance.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }
}
