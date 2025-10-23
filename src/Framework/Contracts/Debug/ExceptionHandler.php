<?php

namespace Framework\Contracts\Debug;

use Throwable;

interface ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $e);

    /**
     * Determine if the exception should be reported.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    public function shouldReport(Throwable $e);

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e);
}
