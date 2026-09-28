<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** An abstract model that inherits Model::getKey()'s `mixed`, with no key type a subclass must keep. */
abstract class ReceiverKeyInheritingModel extends Model {}
