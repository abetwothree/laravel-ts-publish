<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table that implements HasCode, the runtime value of a `Post&HasCode`. */
class CodedPost extends Post implements HasCode
{
    public string $code {
        get => 'C'.$this->getKey();
    }

    public HeldStats $stats {
        get => new HeldStats(2, 'code');
    }

    protected $table = 'posts';

    /** The stats the code was read from. */
    public function codeStats(): HeldStats
    {
        return $this->stats;
    }
}
