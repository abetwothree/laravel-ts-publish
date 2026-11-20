<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A post redeclaring the property its parent's getKeyName() returns with a get hook that clears the pin, so Eloquent's
 * getKey() writes on it.
 */
class HookedKeyPost extends PropertyKeyPost
{
    protected string $keyField {
        get {
            $this->is_pinned = false;

            return 'id';
        }
    }
}
