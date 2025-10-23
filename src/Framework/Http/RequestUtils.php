<?php

namespace Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

class RequestUtils
{
    /**
     * Get the trim path from the request uri.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return string
     */
    public static function path(ServerRequestInterface $request): string
    {
        return trim($request->getUri()->getPath(), '/') ?: '/';
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
     */
    public static function bearerToken(ServerRequestInterface $request): string|null
    {
        $header = $request->getHeaderLine('Authorization') ?? '';

        $position = strripos($header, 'Bearer ');

        if ($position !== false) {
            $header = substr($header, $position + 7);

            return str_contains($header, ',') ?
                strstr($header, ',', true) :
                $header;
        }

        return null;
    }
}
