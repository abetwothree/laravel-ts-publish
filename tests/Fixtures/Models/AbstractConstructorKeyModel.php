<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** An abstract model whose constructor replaces the declared key type. */
abstract class AbstractConstructorKeyModel extends Model
{
    /** Keys every subclass by string after Model's own constructor. */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->keyType = 'string';
    }
}
