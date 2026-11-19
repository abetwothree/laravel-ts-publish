<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Workbench\App\Models\Post;

/**
 * A post whose getKeyName() returns a property it declares.
 */
class PropertyKeyPost extends Post
{
    protected $table = 'posts';

    protected string $keyField = 'id';

    /**
     * Names the key by the property.
     */
    public function getKeyName(): string
    {
        return $this->keyField;
    }
}
