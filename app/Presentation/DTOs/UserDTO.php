<?php

declare(strict_types=1);

namespace App\Presentation\DTOs;

use App\Domain\Entity\User;

class UserDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $role,
        public ?int $phone = null,
    ) {}

    public static function make(
        string $firstName,
        string $lastName,
        string $email,
        string $role,
        ?int $phone = null,
    ) {
        return new static(
            $firstName,
            $lastName,
            $email,
            $role,
            $phone
        );
    }

    public function normalize()
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
        ];
    }

    public function toModel()
    {
        return new User($this->normalize());
    }
}
