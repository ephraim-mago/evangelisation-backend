<?php

namespace App\Presentation\Controllers\Auth;

use App\Domain\Entity\User;
use App\Presentation\Controllers\Controller;
use Psr\Http\Message\ServerRequestInterface;
use App\Infrastructure\Services\AccessTokensService;

class LogoutController extends Controller
{
    public function __construct(
        protected AccessTokensService $accessTokensService
    ) {}

    public function __invoke(ServerRequestInterface $request, User $user)
    {
        $this->accessTokensService->forgetToken($user->currentAccessToken()->id);

        return $this->json(null, 'User logged out');
    }
}
