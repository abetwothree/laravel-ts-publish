<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

class InertiaController
{
    /**
     * Display the dashboard page.
     */
    public function dashboard(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'users' => 100,
                'posts' => 50,
                'views' => 10000,
            ],
            'recentActivity' => [],
        ]);
    }

    /**
     * Display the settings page.
     */
    public function settings(): Response
    {
        return Inertia::render('Settings/General', [
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'preferences' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
        ]);
    }

    /**
     * Display the about page (no props).
     */
    public function about(): Response
    {
        return Inertia::render('About');
    }

    /**
     * Conditional rendering based on auth state.
     */
    public function conditional(): Response
    {
        if (auth()->check()) {
            return Inertia::render('Conditional/Authenticated', [
                'user' => auth()->user(),
            ]);
        }

        return Inertia::render('Conditional/Guest', [
            'message' => 'Please log in',
        ]);
    }

    /**
     * Display a specific post.
     */
    public function post(Post $post): Response
    {
        return Inertia::render('PostShow', [
            'post' => $post,
        ]);
    }
}
