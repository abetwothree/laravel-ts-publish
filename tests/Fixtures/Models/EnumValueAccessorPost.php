<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose getters send its status enum's scalars, or the enum itself. */
class EnumValueAccessorPost extends Post
{
    protected $table = 'posts';

    /** The status's value. */
    protected function statusValue(): Attribute
    {
        return Attribute::get(fn () => $this->status->value);
    }

    /** The status's case name. */
    protected function statusName(): Attribute
    {
        return Attribute::get(fn () => $this->status->name);
    }

    /** The status enum itself, which the model's Resource interface prints as its AsEnum wrap. */
    protected function statusEnum(): Attribute
    {
        return Attribute::get(function (): Status {
            return $this->status;
        });
    }

    /** The status's value, or Draft's until the post is saved. */
    protected function statusOrDraft(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->status->value : Status::Draft->value);
    }

    /** The status itself, or Draft's value until the post is saved. */
    protected function statusCaseOrDraft(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->status : Status::Draft->value);
    }
}
