<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Initialize;
use Illuminate\Database\Eloquent\Model;

/** An abstract model whose #[Initialize] method, named off the `initialize` convention, replaces the key type. */
abstract class AbstractInitializeKeyModel extends Model
{
    /** Keys every subclass by string once the model is built. */
    #[Initialize]
    public function prepareKey(): void
    {
        $this->keyType = 'string';
    }
}
