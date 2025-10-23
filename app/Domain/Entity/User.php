<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;
use Framework\Contracts\Hashing\Hasher;
use Framework\Database\ORM\Model;

/**
 * @property string $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property ?string $password
 * @property ?string $phone
 * @property string $role
 * @property ?\DateTimeImmutable $created_at
 * @property ?\DateTimeImmutable $updated_at
 *
 * @template TToken of object
 */
class User extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected array $hidden = [
        'password',
    ];

    /**
     * The access token the user is using for the current request.
     *
     * @var TToken
     */
    protected $accessToken;

    /**
     * Define user password with given value and hasher implementation.
     *
     * @param string $password
     * @param \Framework\Contracts\Hashing\Hasher $hasher
     * @return $this
     */
    public function definePassword(string $password, Hasher $hasher): self
    {
        $this->password = $hasher->make($password);

        return $this;
    }

    /**
     * Get the access token currently associated with the user.
     *
     * @return TToken
     */
    public function currentAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Set the current access token for the user.
     *
     * @param  TToken  $accessToken
     * @return $this
     */
    public function withAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }
}
