<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class InvokableInertiaController
{
    public function __invoke(): Response
    {
        return Inertia::render('Profile', [
            'name' => 'Jane Doe',
        ]);
    }
}
