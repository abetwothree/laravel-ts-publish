<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * Flags no child class can hook, beside open flags a step further, with a getKey() of their own that is no Eloquent
 * reader.
 */
final class FinalFlags
{
    public bool $open = true;

    public ?OpenFlags $inner = null;

    /**
     * Closes the flags, under the name of an Eloquent reader.
     */
    public function getKey(): int
    {
        $this->open = false;

        return 1;
    }
}
