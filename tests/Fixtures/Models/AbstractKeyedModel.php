<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** An abstract model that declares nothing about its key, so it keeps Model's integer `id`. */
abstract class AbstractKeyedModel extends Model {}
