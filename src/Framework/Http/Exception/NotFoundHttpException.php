<?php

namespace Framework\Http\Exception;

use Throwable;

class NotFoundHttpException extends HttpException
{
    /**
     * Create a new NotFoundHttpException instance.
     * 
     * @param string $message
     * @param mixed $previous
     * @param int $code
     * @param array $headers
     * @return void
     */
    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        int $code = 0,
        array $headers = []
    ) {
        parent::__construct(
            404,
            $message,
            $previous,
            $headers,
            $code
        );
    }
}
