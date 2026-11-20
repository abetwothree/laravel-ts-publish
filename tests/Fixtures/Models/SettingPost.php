<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/**
 * A post whose own `__set()` clears the pin on every write it is handed, beside a public property a write sets in
 * place and a protected one a write from outside the model's family hands to it.
 */
class SettingPost extends Post
{
    public ?string $open_note = null;

    protected $table = 'posts';

    protected ?string $hidden_note = null;

    /**
     * Sets the attribute, and clears the pin with it.
     */
    public function __set($key, $value): void
    {
        parent::__set($key, $value);
        $this->attributes['is_pinned'] = false;
    }
}
