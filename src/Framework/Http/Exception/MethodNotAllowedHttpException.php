<?php

namespace Framework\Http\Exception;

use Throwable;

class MethodNotAllowedHttpException extends HttpException
{

    /**
     * Create a new MethodNotAllowedHttpException instance.
     *
     * @param  string[]  $allow An array of allowed methods
     * @param  string  $message
     * @param  \Throwable|null  $previous
     * @param  int  $code
     * @param  array  $headers
     * @return void
     */
    public function __construct(
        array $allow,
        string $message = '',
        ?Throwable $previous = null,
        int $code = 0,
        array $headers = []
    ) {
        $headers['Allow'] = strtoupper(implode(', ', $allow));

        parent::__construct(
            405,
            $message,
            $previous,
            $headers,
            $code
        );
    }
}
