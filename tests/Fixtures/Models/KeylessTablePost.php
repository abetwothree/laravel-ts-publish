<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A model over the posts table whose #[Table(key: false)] declares no primary key, as a null $primaryKey does.
 * Only Laravel 13.39+ accepts `key: false`, so only a test guarded on that may build it.
 */
#[Table('posts', key: false)]
class KeylessTablePost extends Model {}
