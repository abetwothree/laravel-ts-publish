<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Support\Collection;

/** A service whose methods return a keyed collection and a plain class that declares its own values() and all(). */
final class PostScores
{
    /**
     * Scores keyed by author name, which json_encode() writes as an object.
     *
     * @return Collection<string, int>
     */
    public function byName(): Collection
    {
        return collect(['ann' => 1, 'bob' => 2]);
    }

    /** Settings that are not a collection, so their values() and all() are their own. */
    public function settings(): PostSettings
    {
        return new PostSettings;
    }
}
