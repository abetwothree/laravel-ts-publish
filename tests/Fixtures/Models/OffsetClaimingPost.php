<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/**
 * A post whose own `offsetExists()` claims every attribute, so isset() of one answers apart from reading it, beside a
 * public property isset() reads in place and one the constructor unsets, which it reaches through `__isset()`.
 */
class OffsetClaimingPost extends Post
{
    public ?bool $flagged = null;

    public ?bool $memo = null;

    protected $table = 'posts';

    /**
     * A post whose `memo` reaches `__isset()`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        unset($this->memo);
    }

    /**
     * Claims every attribute.
     */
    public function offsetExists($offset): bool
    {
        return true;
    }
}
