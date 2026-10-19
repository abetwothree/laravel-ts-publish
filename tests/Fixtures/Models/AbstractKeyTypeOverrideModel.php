<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** An abstract model whose getKeyType() is code, so no declaration names its key type. */
abstract class AbstractKeyTypeOverrideModel extends Model
{
    /** Picks the key type at runtime. */
    public function getKeyType()
    {
        return config('app.key_type', 'string');
    }
}
