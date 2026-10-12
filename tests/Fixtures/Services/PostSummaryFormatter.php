<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

/** A service whose helper declares no type for what it is passed. */
final class PostSummaryFormatter
{
    /**
     * A title read off whatever the caller passes.
     *
     * @param  mixed  $post
     * @return array<string, mixed>
     */
    public function row($post): array
    {
        return ['row_title' => $post->title];
    }
}
