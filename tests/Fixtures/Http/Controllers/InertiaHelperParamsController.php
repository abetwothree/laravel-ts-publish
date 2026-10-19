<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

class InertiaHelperParamsController
{
    /**
     * Props built by helpers whose typed parameter their bodies read, as a value and spread.
     */
    public function show(Post $post): Response
    {
        return Inertia::render('Posts/Show', [
            'row' => $this->row($post),
            ...$this->spreadRow($post),
        ]);
    }

    /**
     * The post's title as a nested row.
     *
     * @return array<string, mixed>
     */
    private function row(Post $post): array
    {
        return ['row_title' => $post->title];
    }

    /**
     * The post's title as a spread row.
     *
     * @return array<string, mixed>
     */
    private function spreadRow(Post $post): array
    {
        return ['spread_title' => $post->title];
    }
}
