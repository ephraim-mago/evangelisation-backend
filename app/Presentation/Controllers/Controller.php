<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use Framework\Routing\Controller as BaseController;
use Framework\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;

class Controller extends BaseController
{
    /**
     * Create a controller response.
     *
     * @param mixed $content
     * @param int $status
     * @param array $headers
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function response($content = "", int $status = 200, array $headers = []): ResponseInterface
    {
        return ResponseFactory::make($content, $status, $headers);
    }

    /**
     * Create a controller JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $status
     * @param array $headers
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function json(
        $data = [],
        ?string $message = null,
        int $status = 200,
        array $headers = []
    ): ResponseInterface {
        return ResponseFactory::json(
            $message ? ['message' => $message, 'data' => $data] : $data,
            $status,
            $headers
        );
    }
}
