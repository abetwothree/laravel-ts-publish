<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * An abstract model whose #[Table(key: false)] declares no primary key, as a null $primaryKey does.
 * Only Laravel 13.39+ accepts `key: false`, so only a test guarded on that may build its attribute.
 */
#[Table(key: false)]
abstract class AbstractKeylessTableModel extends Model {}
