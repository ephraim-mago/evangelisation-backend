<?php

namespace Framework\Http\Middleware;

use Closure;
use Framework\Contracts\Container\Container;
use Framework\Http\RequestUtils;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HandleCorsMiddleware
{
    /**
     * The container instance.
     *
     * @var \Framework\Contracts\Container\Container
     */
    protected $container;

    /**
     * The cors configuration.
     * 
     * @var array
     */
    protected array $config;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Closure(\Psr\Http\Message\ServerRequestInterface): (\Psr\Http\Message\ResponseInterface)  $next
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function handle($request, Closure $next): ResponseInterface
    {
        if (!str_contains(RequestUtils::path($request), 'api')) {
            return $next($request);
        }

        $this->config = $this->container['config']->get('cors', []);

        // Vérifie si c'est une requête preflight OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }

        // Traite la requête normale
        $response = $next($request);

        // Ajoute les headers CORS à la réponse
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Gère les requêtes preflight (OPTIONS)
     */
    protected function handlePreflightRequest(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(204); // No Content

        $response = $this->addCorsHeaders($request, $response);

        // Ajoute les headers spécifiques au preflight
        $response = $response
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']))
            ->withHeader('Access-Control-Max-Age', (string) $this->config['max_age']);

        // Gère Access-Control-Allow-Headers
        $requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');

        if ($requestHeaders) {
            $response = $response->withHeader('Access-Control-Allow-Headers', $requestHeaders);
        } elseif (!empty($this->config['allowed_headers'])) {
            $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->config['allowed_headers']));
        }

        return $response;
    }

    /**
     * Ajoute les headers CORS à la réponse
     */
    protected function addCorsHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Détermine l'origin à autoriser
        $allowedOrigin = $this->getAllowedOrigin($origin);

        if ($allowedOrigin) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);

            // Si credentials sont autorisés
            if ($this->config['allow_credentials']) {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }

            // Headers exposés
            if (!empty($this->config['exposed_headers'])) {
                $response = $response->withHeader(
                    'Access-Control-Expose-Headers',
                    implode(', ', $this->config['exposed_headers'])
                );
            }

            // Vary header pour le cache
            $response = $response->withHeader('Vary', 'Origin');
        }

        return $response;
    }

    /**
     * Détermine si l'origin est autorisée
     */
    protected function getAllowedOrigin(string $origin): ?string
    {
        if (empty($origin)) {
            return null;
        }

        // Si * est autorisé et pas de credentials
        if (in_array('*', $this->config['allowed_origins']) && !$this->config['allow_credentials']) {
            return '*';
        }

        // Vérifie si l'origin est dans la liste
        if (in_array($origin, $this->config['allowed_origins'])) {
            return $origin;
        }

        // Vérifie avec des wildcards (ex: *.example.com)
        foreach ($this->config['allowed_origins'] as $allowedOrigin) {
            if ($this->matchWildcard($allowedOrigin, $origin)) {
                return $origin;
            }
        }

        return null;
    }

    /**
     * Vérifie si l'origin correspond au pattern avec wildcard
     */
    protected function matchWildcard(string $pattern, string $origin): bool
    {
        if ($pattern === '*') {
            return true;
        }

        // Convertit le pattern en regex
        $pattern = str_replace(['.', '*'], ['\.', '.*'], $pattern);
        $regex = '/^' . $pattern . '$/i';

        return (bool) preg_match($regex, $origin);
    }
}
