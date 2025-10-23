<?php

namespace Framework\Core\Exceptions;

use Closure;
use WeakMap;
use Throwable;
use Framework\Collection\Arr;
use InvalidArgumentException;
use Framework\Http\ResponseFactory;
use Framework\Contracts\Debug\Renderer;
use Framework\Auth\AuthenticationException;
use Framework\Http\Exception\HttpException;
use Framework\Contracts\Container\Container;
use Framework\Contracts\Support\Responsable;
use Framework\Http\Helpers\AcceptJsonHelper;
use Framework\Validation\ValidationException;
use Framework\Http\Exception\HttpResponseException;
use Framework\Core\Exceptions\Renderer\SimpleRenderer;
use Framework\Core\Exceptions\Renderer\WhoopsRenderer;
use Framework\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;

class Handler implements ExceptionHandlerContract
{
    /**
     * The container implementation.
     *
     * @var \Framework\Contracts\Container\Container
     */
    protected $container;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [];

    /**
     * The registered exception mappings.
     *
     * @var array<string, \Closure>
     */
    protected $exceptionMap = [];

    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $internalDontReport = [
        AuthenticationException::class,
        // AuthorizationException::class,
        HttpException::class,
        HttpResponseException::class,
        ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Indicates that an exception instance should only be reported once.
     *
     * @var bool
     */
    protected $withoutDuplicates = false;

    /**
     * The already reported exception map.
     *
     * @var \WeakMap
     */
    protected $reportedExceptionMap;

    /**
     * Create a new exception handler instance.
     *
     * @param  \Framework\Contracts\Container\Container  $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->reportedExceptionMap = new WeakMap;
    }

    /**
     * Register a new exception mapping.
     *
     * @param  \Closure|string  $from
     * @param  \Closure|string|null  $to
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function map($from, $to = null)
    {
        if (is_string($to)) {
            $to = fn($exception) => new $to('', 0, $exception);
        }

        if (! is_string($from) || ! $to instanceof Closure) {
            throw new InvalidArgumentException('Invalid exception mapping.');
        }

        $this->exceptionMap[$from] = $to;

        return $this;
    }

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $e)
    {
        //
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    public function shouldReport(Throwable $e)
    {
        return ! $this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e)
    {
        if ($this->withoutDuplicates && ($this->reportedExceptionMap[$e] ?? false)) {
            return true;
        }

        $dontReport = array_merge($this->dontReport, $this->internalDontReport);

        if (! is_null(Arr::first(
            $dontReport,
            fn($type) => $e instanceof $type
        ))) {
            return true;
        }

        return false;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof Responsable) {
            return $e->toResponse($request);
        }

        $e = $this->prepareException($e);

        return match (true) {
            $e instanceof HttpResponseException => $e->getResponse(),
            $e instanceof AuthenticationException => $this->unauthenticated($request, $e),
            $e instanceof ValidationException => $this->convertValidationExceptionToResponse($e, $request),
            default => $this->renderExceptionResponse($request, $e),
        };
    }

    /**
     * Prepare exception for rendering.
     *
     * @param  \Throwable  $e
     * @return \Throwable
     */
    protected function prepareException(Throwable $e)
    {
        return match (true) {
            default => $e,
        };
    }


    /**
     * Render a default exception response if any.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function renderExceptionResponse($request, Throwable $e)
    {
        return $this->shouldReturnJson($request, $e)
            ? $this->prepareJsonResponse($request, $e)
            : $this->prepareResponse($request, $e);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Framework\Auth\AuthenticationException  $exception
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return $this->shouldReturnJson($request, $exception)
            ? ResponseFactory::json(['message' => $exception->getMessage()], 401)
            : ResponseFactory::redirectTo($exception->redirectTo() ?? '/login');
    }

    /**
     * Create a response object from the given validation exception.
     *
     * @param  \Framework\Validation\ValidationException  $e
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function convertValidationExceptionToResponse(ValidationException $e, $request)
    {
        return $this->shouldReturnJson($request, $e)
            ? ResponseFactory::json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->getStatus())
            : throw $e;
    }

    /**
     * Determine if the exception handler response should be JSON.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return bool
     */
    protected function shouldReturnJson($request, Throwable $e)
    {
        return AcceptJsonHelper::wantsJson($request);
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function prepareResponse($request, Throwable $e)
    {
        if (! $e instanceof HttpException) {
            $e = new HttpException(500, $e->getMessage(), $e);
        }

        return ResponseFactory::make(
            $this->renderExceptionContent($e),
            $e instanceof HttpException ? $e->getStatusCode() : 500,
            $e instanceof HttpException ? $e->getHeaders() : []
        )
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Get the response content for the given exception.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function renderExceptionContent(Throwable $e)
    {
        return $this->getRenderer()->render($e);
    }

    /**
     * Get the renderer depends a app config:debug value.
     *
     * @return \Framework\Contracts\Debug\Renderer
     */
    protected function getRenderer(): Renderer
    {
        return config('app.debug') ? new WhoopsRenderer() : new SimpleRenderer();
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function prepareJsonResponse($request, Throwable $e)
    {
        return ResponseFactory::json(
            $this->convertExceptionToArray($e),
            $e instanceof HttpException ? $e->getStatusCode() : 500,
            $e instanceof HttpException ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Convert the given exception to an array.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function convertExceptionToArray(Throwable $e)
    {
        return config('app.debug') ? [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => Arr::map(
                $e->getTrace(),
                fn($trace) => Arr::except($trace, ['args'])
            ),
        ] : [
            'message' => $e instanceof HttpException ? $e->getMessage() : 'Server Error',
        ];
    }
}
