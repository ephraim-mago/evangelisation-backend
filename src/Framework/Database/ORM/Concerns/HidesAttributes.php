<?php

declare(strict_types=1);

namespace Framework\Database\ORM\Concerns;

trait HidesAttributes
{
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array<string>
     */
    protected array $visible = [];

    /**
     * Get the hidden attributes for the model.
     *
     * @return array<string>
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param  array<string>  $hidden
     * @return $this
     */
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return array<string>
     */
    public function getVisible(): array
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     *
     * @param  array<string>  $visible
     * @return $this
     */
    public function setVisible(array $visible): static
    {
        $this->visible = $visible;

        return $this;
    }
}
