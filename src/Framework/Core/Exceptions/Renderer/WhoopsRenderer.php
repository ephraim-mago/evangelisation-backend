<?php

namespace Framework\Core\Exceptions\Renderer;

use Throwable;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Framework\Contracts\Debug\Renderer;

class WhoopsRenderer implements Renderer
{
    public function render(Throwable $e): string
    {
        $whoops = new Run;
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);
        $whoops->pushHandler(new PrettyPageHandler);

        return $whoops->handleException($e);
    }
}
