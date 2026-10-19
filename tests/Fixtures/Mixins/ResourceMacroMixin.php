<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Mixins;

use Closure;

/** A mixin whose methods each hand JsonResource::mixin() the closure it registers. */
class ResourceMacroMixin
{
    /** The `badge` macro: a string label. */
    public function badge(): Closure
    {
        return fn (): string => 'gold';
    }

    /** The `wordCount` macro, typed only by its body. */
    public function wordCount(): Closure
    {
        return function () {
            return str_word_count((string) $this->resource->title);
        };
    }

    /** The `titleLength` macro, typed only by its body. */
    public function titleLength(): Closure
    {
        return function () {
            return strlen((string) $this->resource->title);
        };
    }
}
