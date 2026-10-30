<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` passes an array to its parent's overridden `decorate()`. */
class DecoratingBase extends DecoratingGrand
{
    /**
     * Adds the `base` key to the array.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    public static function decorate(array $d): array
    {
        $d['base'] = 'x';

        return $d;
    }

    /**
     * The payload, decorated by the parent's `decorate()`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['v' => parent::decorate(['id' => 1])];
    }
}
