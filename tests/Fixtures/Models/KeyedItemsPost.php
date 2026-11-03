<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\KeysItems;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Post;

/** A model binding KeysItems' key and item templates through `@use`. */
class KeyedItemsPost extends Model
{
    /** @use KeysItems<int, Post> */
    use KeysItems;

    protected $table = 'posts';
}
