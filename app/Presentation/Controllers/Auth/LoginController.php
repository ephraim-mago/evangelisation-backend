<?php

namespace App\Presentation\Controllers\Auth;

use Framework\Validation\Validator;
use Framework\Contracts\Hashing\Hasher;
use App\Presentation\Controllers\Controller;
use Psr\Http\Message\ServerRequestInterface;
use Framework\Validation\ValidationException;
use App\Infrastructure\Repositories\UserDatabaseRepository;
use App\Infrastructure\Services\AccessTokensService;

class LoginController extends Controller
{
    public function __construct(
        protected UserDatabaseRepository $userRepository,
        protected Hasher $hasher,
        protected AccessTokensService $accessTokensService
    ) {}

    public function __invoke(ServerRequestInterface $request)
    {
        $data = Validator::validate($request, [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = $this->userRepository->findOneBy(
            ['email = ?'],
            [$data['email']]
        );

        if (!$user || !$this->hasher->check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $token = $this->accessTokensService->createToken($user);

        return $this->json([
            'user' => $user,
            'token' => $token
        ], 'User authenticated');
    }
}
