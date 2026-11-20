<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A post that unsets the property its parent's getKeyName() returns by a quoted name, so the read runs its
 * `__get()`, which clears the pin, and Eloquent's getKey() writes on it.
 */
class QuotedUnsetKeyPost extends PropertyKeyPost
{
    /**
     * Unsets the key's property, leaving its reads to `__get()`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        unset($this->{'keyField'});
    }

    /**
     * Clears the pin as it names the key.
     */
    public function __get($key)
    {
        if ($key === 'keyField') {
            $this->attributes['is_pinned'] = false;

            return 'id';
        }

        return parent::__get($key);
    }
}
