<?php

namespace Framework\Contracts\Debug;

use Throwable;

interface Renderer
{
    public function render(Throwable $e): string;
}
