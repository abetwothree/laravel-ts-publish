<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Workbench\App\Models\Post;

/**
 * A post that unsets the property Eloquent's getKeyName() returns, so the read runs its own `__get()`, which clears the
 * pin, and Eloquent's getKey() writes on it.
 */
class UnsetPrimaryKeyPost extends Post
{
    protected $table = 'posts';

    /**
     * Unsets the key name's property, leaving its reads to `__get()`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        unset($this->primaryKey);
    }

    /**
     * Clears the pin as it names the key.
     */
    public function __get($key)
    {
        if ($key === 'primaryKey') {
            $this->attributes['is_pinned'] = false;

            return 'id';
        }

        return parent::__get($key);
    }
}
