<?php

namespace Framework\Http;

use stdClass;
use Stringable;
use ArrayObject;
use JsonSerializable;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Framework\Contracts\Support\Jsonable;
use Framework\Contracts\Support\Arrayable;
use Framework\Contracts\Support\Responsable;

class ResponseFactory
{
    /**
     * Create a response instance from the given value.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  mixed  $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function prepareResponse($request, $response)
    {
        if ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }

        if ($response instanceof Stringable) {
            $response = static::make(
                $response->__toString(),
                200,
                ['Content-Type' => 'text/html'],
            );
        } elseif (
            ! $response instanceof ResponseInterface &&
            ($response instanceof Arrayable ||
                $response instanceof Jsonable ||
                $response instanceof ArrayObject ||
                $response instanceof JsonSerializable ||
                $response instanceof stdClass ||
                is_array($response))
        ) {
            $response = static::json($response);
        } elseif (! $response instanceof ResponseInterface) {
            $response = static::make($response, 200, ['Content-Type' => 'text/html']);
        }

        return $response;
    }

    /**
     * Create a new response instance.
     *
     * @param  mixed  $content
     * @param  int  $status
     * @param  array  $headers
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function make($content = '', $status = 200, array $headers = []): ResponseInterface
    {
        return new Response(
            $status,
            $headers,
            $content
        );
    }

    /**
     * Create a new "no content" response.
     *
     * @param  mixed  $content
     * @param  int  $status
     * @param  array  $headers
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function noContent($status = 204, array $headers = []): ResponseInterface
    {
        return static::make(
            "",
            $status,
            $headers
        );
    }

    /**
     * Create a new JSON response instance.
     *
     * @param  mixed  $data
     * @param  int  $status
     * @param  array  $headers
     * @param  int  $options
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function json(
        $data = [],
        $status = 200,
        array $headers = [],
        $options = 0
    ): ResponseInterface {
        // Ensure json_last_error() is cleared...
        json_decode('[]');

        $options = JSON_PRETTY_PRINT | $options;

        $data = match (true) {
            $data instanceof Jsonable => $data->toJson($options),
            $data instanceof JsonSerializable => json_encode(
                $data->jsonSerialize(),
                $options
            ),
            $data instanceof Arrayable => json_encode(
                $data->toArray(),
                $options
            ),
            default => json_encode($data, $options),
        };

        return static::make(
            $data,
            $status,
            array_merge($headers, [
                'Content-Type' => "application/json"
            ]),
        );
    }

    /**
     * Create a new redirect response to the given url.
     *
     * @param  string  $url
     * @param  int  $status
     * @param  array  $headers
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function redirectTo($url, $status = 302, array $headers = []): ResponseInterface
    {
        $content = sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, \ENT_QUOTES, 'UTF-8'));

        return static::make(
            $content,
            $status,
            array_merge($headers, [
                'Location' => $url,
                'Content-Type' => 'text/html; charset=utf-8'
            ]),
        );
    }
}
