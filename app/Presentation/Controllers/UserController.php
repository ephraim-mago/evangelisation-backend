<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Infrastructure\Repositories\UserDatabaseRepository;
use App\Presentation\DTOs\UserDTO;
use Framework\Contracts\Hashing\Hasher;
use Framework\Database\Connection;
use Framework\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;
use Framework\Http\Exception\NotFoundHttpException;

class UserController extends Controller
{
    public function __construct(
        protected UserDatabaseRepository $userRepository,
        protected Hasher $hasher,
        protected Connection $connection
    ) {}

    public function index()
    {
        return $this->json(
            $this->userRepository->all(),
            "List of users"
        );
    }

    public function store(ServerRequestInterface $request)
    {
        $data = Validator::validate($request, [
            'firstName' => ['required'],
            'lastName' => ['required'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'integer'],
            'role' => ['required'],
        ]);

        $user = UserDTO::make(...$data)->toModel();
        $user->definePassword("password@1234", $this->hasher);

        $this->userRepository->save($user);

        return $this->json(
            $data,
            'User data saved',
            status: 201
        );
    }

    public function show(ServerRequestInterface $request, string $id)
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        return $this->json($user);
    }

    public function update(ServerRequestInterface $request, string $id)
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        $data = Validator::validate($request, [
            'firstName' => ['required'],
            'lastName' => ['required'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'integer'],
            'role' => ['required'],
        ]);

        $data = UserDTO::make(...$data)->normalize();

        $user = $this->userRepository->update($user, $data);

        return $this->json($user, 'User data updated');
    }

    public function destroy(ServerRequestInterface $request, string $id)
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        $this->userRepository->delete($user);

        return $this->json(null, 'User delected');
    }
}
