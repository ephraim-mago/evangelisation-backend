<?php

namespace App\Infrastructure\Factories;

class IDFactory
{
    /**
     * Generate ID with given prefix.
     *
     * @param string|null $prefix
     * @return string
     */
    public static function generateID(?string $prefix = null): string
    {
        return uniqid(
            $prefix === null ? "" : $prefix
        );
    }
}
