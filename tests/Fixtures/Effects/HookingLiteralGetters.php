<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A subclass of LiteralGetters: a get hook on a property its parent reads and on one of a name its parent keeps
 * private, a property its parent lacks, and a private one its parent reads through `__get()`.
 */
class HookingLiteralGetters extends LiteralGetters
{
    public string $own {
        get {
            $this->names = [];

            return 'uuid';
        }
    }

    protected string $keyName {
        get {
            $this->names = [];

            return 'uuid';
        }
    }

    protected string $belowKey = 'id';

    private string $missing = 'missing';
}
