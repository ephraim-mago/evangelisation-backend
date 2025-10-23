<?php

namespace App\Infrastructure\Services;

use App\Domain\Entity\User;
use DateTimeImmutable;
use DateTimeInterface;
use Framework\Support\Str;
use Framework\Database\Connection;
use Framework\Database\Query\Builder as QueryBuilder;

class AccessTokensService
{
    /**
     * The connection for the repository.
     *
     * @var \Framework\Database\Connection
     */
    protected Connection $connection;

    /**
     * Create a Repository instance.
     *
     * @param \Framework\Database\Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param  \App\Domain\Entity\User  $user
     * @param  string  $name
     * @param  array  $abilities
     * @param  \DateTimeInterface|null  $expiresAt
     * @return string
     */
    public function createToken(
        User $user,
        string $name = 'auth_token',
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ) {
        $plainTextToken = $this->generateTokenString();

        $payload = [
            'tokenable_type' => get_class($user),
            'tokenable_id' => $user->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => json_encode($abilities),
            'expires_at' => $expiresAt,
        ];

        $lastId = $this->table()->insertGetId($payload, 'id');

        return $lastId . '|' . $plainTextToken;
    }

    /**
     * Find the token instance matching the given token.
     *
     * @param  string  $token
     * @return object|null
     */
    public function findToken($token): ?object
    {
        if (strpos($token, '|') === false) {
            return $this->connection->selectOne(
                "SELECT * FROM personal_access_tokens WHERE token = ?",
                [hash('sha256', $token)]
            );
        }

        [$id, $token] = explode('|', $token, 2);

        if ($instance = $this->connection->selectOne(
            "SELECT * FROM personal_access_tokens WHERE id = ?",
            [$id]
        )) {
            return hash_equals(
                $instance->token,
                hash('sha256', $token)
            ) ? $instance : null;
        }

        return null;
    }

    /**
     * Find the token instance matching the given token.
     *
     * @param  int  $token
     * @return bool
     */
    public function forgetToken(int $id): bool
    {
        return $this->table()->delete(["id = ?"], [$id]);
    }

    public function markAsUsed(int $id)
    {
        return $this->table()->update(["id = :id"], [
            'last_used_at' => new DateTimeImmutable(),
            'id' => $id
        ]);
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    protected function generateTokenString()
    {
        return sprintf(
            '%s%s',
            $tokenEntropy = Str::random(40),
            hash('crc32b', $tokenEntropy)
        );
    }

    protected function table(): QueryBuilder
    {
        return $this->connection->table('personal_access_tokens');
    }
}
