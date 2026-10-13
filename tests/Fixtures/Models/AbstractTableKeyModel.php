<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** An abstract model whose Laravel 13 #[Table] declares a string key, which every subclass inherits. */
#[Table(keyType: 'string')]
abstract class AbstractTableKeyModel extends Model {}
