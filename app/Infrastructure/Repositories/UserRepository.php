<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use DateTimeImmutable;
use SplFileInfo;
use FilesystemIterator;
use App\Domain\Entity\User;
use Framework\Collection\Arr;
use Framework\Filesystem\Filesystem;

class UserRepository
{
    /**
     * The filesystem instance.
     *
     * @var \Framework\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * The files directory name.
     *
     * @var string
     */
    protected $directory = "users";

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }


    public function save(User $user): User
    {
        $savedPath = storage_path(
            'app' . DIRECTORY_SEPARATOR .
                'database' . DIRECTORY_SEPARATOR .
                $this->directory
        );

        $this->filesystem->ensureDirectoryExists($savedPath);



        $this->filesystem->put(
            $savedPath . '/' . $user->id . '.json',
            json_encode(
                array_merge(
                    (array) $user,
                    [
                        'createdAt' => $user->createdAt instanceof DateTimeImmutable ? $user->createdAt->format(DATE_ATOM) : $user->createdAt,
                        'updatedAt' => $user->updatedAt instanceof DateTimeImmutable ? $user->updatedAt->format(DATE_ATOM) : $user->updatedAt
                    ]
                ),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
        );

        return $user;
    }

    public function all(): array
    {
        $retrievePath = storage_path(
            'app' . DIRECTORY_SEPARATOR .
                'database' . DIRECTORY_SEPARATOR .
                $this->directory
        );


        $files = iterator_to_array(new FilesystemIterator(
            $retrievePath,
            FilesystemIterator::SKIP_DOTS
        ));

        $data = Arr::map($files, function (SplFileInfo $file): User {
            $item = $this->filesystem->json($file->getRealPath());

            return new User(...$item);
        });

        return array_values($data);
    }

    public function findById(string $id): User|null
    {
        $retrievePath = storage_path(
            'app' . DIRECTORY_SEPARATOR .
                'database' . DIRECTORY_SEPARATOR .
                $this->directory
        );

        $files = iterator_to_array(new FilesystemIterator(
            $retrievePath,
            FilesystemIterator::SKIP_DOTS
        ));

        $data = Arr::first(
            $files,
            fn(SplFileInfo $file) => $file->getFilename() === $id . '.json'
        );

        return $data ?
            new User(...$this->filesystem->json($data->getRealPath())) :
            null;
    }

    public function delete(User $user): bool
    {
        $retrievePath = storage_path(
            'app' . DIRECTORY_SEPARATOR .
                'database' . DIRECTORY_SEPARATOR .
                $this->directory
        );

        $files = iterator_to_array(new FilesystemIterator(
            $retrievePath,
            FilesystemIterator::SKIP_DOTS
        ));

        $data = Arr::first(
            $files,
            fn(SplFileInfo $file) => $file->getFilename() === $user->id . '.json'
        );

        return $this->filesystem->delete($data->getRealPath());
    }
}
