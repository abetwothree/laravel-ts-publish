<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** A real Inertia middleware whose share() casts `locale`, for a subclass to spread through parent::share(). */
class ShareCastBaseMiddleware extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['locale' => "'en' | 'es'"])]
    public function share(Request $request): array
    {
        return ['locale' => 'en'];
    }
}
