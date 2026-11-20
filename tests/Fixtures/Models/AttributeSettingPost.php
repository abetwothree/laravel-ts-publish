<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\Post;

/**
 * A post whose own `setAttribute()` clears the pin on every write Eloquent's `__set()` hands it, beside a public
 * property a write sets in place and a protected one a write from outside the model's family hands to `__set()`.
 */
class AttributeSettingPost extends Post
{
    public ?string $open_note = null;

    protected $table = 'posts';

    protected ?string $hidden_note = null;

    /**
     * Sets the attribute, and clears the pin with it.
     */
    public function setAttribute($key, $value): mixed
    {
        parent::setAttribute($key, $value);
        $this->attributes['is_pinned'] = false;

        return $this;
    }
}
