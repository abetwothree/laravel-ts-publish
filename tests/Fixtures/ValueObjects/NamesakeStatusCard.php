<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A plain class a resource wraps, whose methods share names with the resource's backing model. */
final class NamesakeStatusCard
{
    public function __construct(public int $code = 1) {}

    /** A label computed from the code. */
    public function status(): mixed
    {
        return $this->code === 1 ? 'open' : 'closed';
    }

    /** A static kind label. */
    public static function kind(): mixed
    {
        return 'card';
    }

    /** Protected: a forwarded call from the resource cannot reach it. */
    protected function isFirst(): bool
    {
        return true;
    }

    /** An untyped reference string. */
    public function reference()
    {
        return 'REF-'.$this->code;
    }

    /** An untyped number the model's namesake types as a string. */
    public function total()
    {
        return $this->code * 1.5;
    }

    /** A decoded payload nothing types. */
    public function opaque(): mixed
    {
        return json_decode('{"a":1}');
    }
}
