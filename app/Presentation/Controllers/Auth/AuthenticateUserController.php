<?php

namespace App\Presentation\Controllers\Auth;

use App\Domain\Entity\User;
use App\Presentation\Controllers\Controller;
use Psr\Http\Message\ServerRequestInterface;

class AuthenticateUserController extends Controller
{
    public function __construct()
    {
        // $this->middleware('admin');
    }

    public function __invoke(ServerRequestInterface $request, User $user)
    {
        return $user;
    }
}
