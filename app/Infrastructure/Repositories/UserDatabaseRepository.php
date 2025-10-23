<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entity\User;
use App\Infrastructure\Factories\IDFactory;
use Framework\Database\ORM\Repository;

class UserDatabaseRepository extends Repository
{
    protected string|null $table = "users";

    protected string|null $model = User::class;

    /**
     * Get a collection of models.
     *
     * @param  array  $items
     * @return array<int, User>
     */
    public function all(): array
    {
        return $this->hydrate(
            $this->connection->select("SELECT * FROM {$this->table}")
        );
    }

    public function find(string|int $id): ?User
    {
        $item = $this->connection->selectOne(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );

        return $item ? $this->newFromBuilder((array) $item) : null;
    }

    public function findBy(array $criteria, array $values): array
    {
        return $this->hydrate($this->connection->select(
            "SELECT * FROM {$this->table} WHERE " . join(' AND ', $criteria),
            $values
        ));
    }

    public function findOneBy(array $criteria, array $values): ?User
    {
        $item = $this->connection->selectOne(
            "SELECT * FROM {$this->table} WHERE " . join(' AND ', $criteria),
            $values
        );

        return $item ? $this->newFromBuilder((array) $item) : null;
    }

    public function save(User $user): User
    {
        $user->setAttribute(
            $user->getKeyName(),
            IDFactory::generateID('US')
        );

        $this->table()->insert($user->getAttributes());

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->fill($data);

        $this->table()->update([
            "{$user->getKeyName()} = :{$user->getKeyName()}"
        ], $user->getAttributes());

        return $user;
    }

    public function delete(User $user): bool
    {
        return $this->table()->delete([
            "{$user->getKeyName()} = :{$user->getKeyName()}"
        ], [
            $user->getKeyName() => $user->getKey(),
        ]);
    }
}
