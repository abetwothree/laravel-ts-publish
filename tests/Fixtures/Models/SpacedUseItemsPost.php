<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\KeysItems;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Post;

/** A model binding KeysItems' templates through a `@use` tag spaced before its arguments. */
class SpacedUseItemsPost extends Model
{
    /** @use KeysItems <int, Post> */
    use KeysItems;

    protected $table = 'posts';
}
