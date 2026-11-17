<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** A test-only post on the `posts` table whose getters send its status enum's scalars, the enum itself, or both. */
class EnumValueAccessorPost extends Post
{
    protected $table = 'posts';

    protected $appends = ['appended_status_or_draft'];

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

    /** The status's value once saved, else the status itself. */
    protected function statusValueOrCase(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->status->value : $this->status);
    }

    /** The status itself once saved, else its value. */
    protected function statusCaseOrValue(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->status : $this->status->value);
    }

    /** The status_or_draft accessor, read back. */
    protected function statusOrDraftRead(): Attribute
    {
        return Attribute::get(fn () => $this->status_or_draft);
    }

    /** The status_or_draft accessor once saved, else the Published case. */
    protected function statusOrDraftOrPublished(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->status_or_draft : Status::Published);
    }

    /** The priority wrapped once saved, its value when pinned, else Low's value. */
    protected function priorityWrapValueOrLow(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->exists => EnumResource::make($this->priority),
            $this->is_pinned => $this->priority->value,
            default => Priority::Low->value,
        });
    }

    /** High wrapped once saved, Medium's value when pinned, else Low's value. */
    protected function priorityHighWrapValueOrLow(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->exists => EnumResource::make(Priority::High),
            $this->is_pinned => Priority::from(1)->value,
            default => Priority::Low->value,
        });
    }

    /** The status's value, or Draft's until the post is saved, appended to the model's array. */
    protected function appendedStatusOrDraft(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->status->value : Status::Draft->value);
    }

    /** The featured image column, read as the status's value, or Draft's until the post is saved. */
    protected function featuredImageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->status->value : Status::Draft->value);
    }

    /** The category column, read as the status itself, or Draft's value until the post is saved. */
    protected function category(): Attribute
    {
        return Attribute::get(fn () => $this->exists ? $this->status : Status::Draft->value);
    }
}
