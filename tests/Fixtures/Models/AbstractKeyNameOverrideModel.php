<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** An abstract model whose getKeyName() is code. */
abstract class AbstractKeyNameOverrideModel extends Model
{
    /** Names the key at runtime. */
    public function getKeyName()
    {
        return 'code';
    }
}
