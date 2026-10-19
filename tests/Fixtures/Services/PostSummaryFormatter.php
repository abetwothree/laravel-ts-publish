<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

/** A service whose helpers declare no type for what they are passed, beside a public and a protected heading. */
final class PostSummaryFormatter
{
    /**
     * A title read off whatever the caller passes, from a static helper.
     *
     * @param  mixed  $post
     * @return array<string, mixed>
     */
    public static function staticRow($post): array
    {
        return ['static_title' => $post->title];
    }

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

    /** The heading a caller outside the class can read. */
    public function title(): string
    {
        return $this->heading();
    }

    /** The heading only the class itself can read. */
    protected function heading(): string
    {
        return 'Summary';
    }
}
