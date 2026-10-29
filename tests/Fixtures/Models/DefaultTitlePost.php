<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** A test-only model on the `posts` table whose new instance already holds a title, as its default attributes set. */
class DefaultTitlePost extends Model
{
    protected $table = 'posts';

    /** @var array<string, mixed> */
    protected $attributes = ['title' => 'Untitled'];
}
