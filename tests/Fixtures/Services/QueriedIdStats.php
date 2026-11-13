<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use Workbench\App\Models\Post;

/** Builds page props from a query chain past its builder, as a collaborator a controller holds. */
class QueriedIdStats
{
    /**
     * The first post's id, read past the query's pluck().
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return ['firstId' => Post::query()->pluck('id')->first()];
    }
}
