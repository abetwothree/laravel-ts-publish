<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Table;

/** A trait whose Laravel 13 #[Table] declares a string key for the model using it. */
#[Table(keyType: 'string')]
trait DeclaresStringTableKey {}
