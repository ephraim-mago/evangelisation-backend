<?php

namespace Framework\Core\Exceptions\Renderer;

use Throwable;
use Framework\Contracts\Debug\Renderer;
use Framework\Http\Exception\HttpException;

class SimpleRenderer implements Renderer
{
    public function render(Throwable $e): string
    {
        $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;
        $errorType = $this->getErrorType($statusCode);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error {$statusCode} - {$errorType}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        .error-type {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
        }
        .error-message {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .back-link {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .back-link:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">{$statusCode}</div>
        <div class="error-type">{$errorType}</div>
        <div class="error-message">{$message}</div>
HTML;
        $html .= <<<HTML
        <a href="/" class="back-link">← Go Home</a>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    protected function getErrorType(int $statusCode): string
    {
        $errors = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];

        return $errors[$statusCode] ?? 'Error';
    }
}
