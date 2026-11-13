<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\App\Models\Post;

/** A post whose own method and accessor read its attributes through getAttribute(). */
class AttributeCardPost extends Post
{
    protected $table = 'posts';

    /** @return array<string, mixed> */
    public function card(): array
    {
        return [
            't' => $this->getAttribute('title'),
            'p' => $this->title,
            'acc' => $this->getAttribute('title_display'),
        ];
    }

    /** The title, read through getAttribute(). */
    protected function shout(): Attribute
    {
        return Attribute::get(fn () => $this->getAttribute('title'));
    }
}
