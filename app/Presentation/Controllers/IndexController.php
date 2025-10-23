<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

class IndexController extends Controller
{
    public function __invoke()
    {
        return $this->json([
            'message' => "Welcome to the api",
            'requested_at' => date('Y-m-d H:i:s')
        ]);
    }
}
