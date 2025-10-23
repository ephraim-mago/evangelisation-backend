<?php

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Framework\Http\RequestUtils;
use Framework\Auth\AuthenticationException;
use Framework\Http\Helpers\AcceptJsonHelper;
use Psr\Http\Message\ServerRequestInterface;
use App\Infrastructure\Services\AccessTokensService;
use App\Infrastructure\Repositories\UserDatabaseRepository;

class Authenticate
{
    public function __construct(
        protected UserDatabaseRepository $userRepository,
        protected AccessTokensService $accessTokensService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request
     * @param  \Closure  $next
     * @param  string  ...$guards
     * @return mixed
     *
     * @throws \Framework\Auth\AuthenticationException
     */
    public function handle(ServerRequestInterface $request, Closure $next, ...$guards)
    {
        if ($newRequest = $this->authenticate($request, $guards)) {
            return $next($newRequest);
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  array  $guards
     * @return \Psr\Http\Message\ServerRequestInterface|null
     *
     * @throws \Framework\Auth\AuthenticationException
     */
    protected function authenticate(ServerRequestInterface $request, array $guards): ?ServerRequestInterface
    {
        if ($token = RequestUtils::bearerToken($request)) {
            $accessToken = $this->accessTokensService->findToken($token);

            if (
                !$accessToken ||
                !$tokenable = $this->userRepository->find($accessToken->tokenable_id)
            ) {
                return null;
            }

            if ($accessToken->tokenable_type !== get_class($tokenable)) {
                return null;
            }

            $tokenable = $tokenable->withAccessToken($accessToken);

            $this->accessTokensService->markAsUsed($accessToken->id);

            return $request->withAttribute('user', $tokenable);
        }


        $this->unauthenticated($request, $guards);
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  array  $guards
     * @return never
     *
     * @throws \Framework\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            AcceptJsonHelper::wantsJson($request) ? null : '/login',
        );
    }
}
