<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose appended accessors read its status's name, and its value or name. */
class EnumNamePost extends Post
{
    protected $table = 'posts';

    /** @var list<string> */
    protected $appends = ['status_name', 'status_both'];

    /**
     * The status's case name; the getter's body types it, as no generic declares it.
     */
    protected function statusName(): Attribute
    {
        return Attribute::get(fn () => $this->status->name);
    }

    /**
     * The status's value on a pinned post, else its case name.
     */
    protected function statusBoth(): Attribute
    {
        return Attribute::get(fn () => $this->is_pinned ? $this->status->value : $this->status->name);
    }
}
