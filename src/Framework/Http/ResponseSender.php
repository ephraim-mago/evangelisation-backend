<?php

namespace Framework\Http;

use Psr\Http\Message\ResponseInterface;

class ResponseSender
{
    /**
     * @var int
     */
    private int $responseChunkSize;

    /**
     * @param int $responseChunkSize
     */
    public function __construct(int $responseChunkSize = 4096)
    {
        $this->responseChunkSize = $responseChunkSize;
    }

    /**
     * Send the response the client
     */
    public function send(ResponseInterface $response): void
    {
        $isEmpty = $this->isResponseEmpty($response);

        if (headers_sent() === false) {
            $this->sendHeaders($response);

            $this->sendStatusLine($response);
        }

        if (!$isEmpty) {
            $this->sendBody($response);
        }
    }

    /**
     * Send Response Headers
     */
    private function sendHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $first = strtolower($name) !== 'set-cookie';

            foreach ($values as $value) {
                $header = sprintf('%s: %s', $name, $value);

                header($header, $first);

                $first = false;
            }
        }
    }

    /**
     * Send Status Line
     */
    private function sendStatusLine(ResponseInterface $response): void
    {
        $statusLine = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        header($statusLine, true, $response->getStatusCode());
    }

    /**
     * Send Body
     */
    private function sendBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $amountToRead = (int) $response->getHeaderLine('Content-Length');

        if (!$amountToRead) {
            $amountToRead = $body->getSize();
        }

        if ($amountToRead) {
            while ($amountToRead > 0 && !$body->eof()) {
                $length = min($this->responseChunkSize, $amountToRead);
                $data = $body->read($length);

                echo $data;

                $amountToRead -= strlen($data);

                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        } else {
            while (!$body->eof()) {
                echo $body->read($this->responseChunkSize);

                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        }
    }

    /**
     * Asserts response body is empty or status code is 204, 205 or 304
     */
    public function isResponseEmpty(ResponseInterface $response): bool
    {
        if (in_array($response->getStatusCode(), [204, 205, 304], true)) {
            return true;
        }

        $stream = $response->getBody();
        $seekable = $stream->isSeekable();

        if ($seekable) {
            $stream->rewind();
        }

        return $seekable ? $stream->read(1) === '' : $stream->eof();
    }
}
