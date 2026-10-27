<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\PairsWithSelf;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;

/**
 * A model on the `posts` table whose helper parameters, closure and macros spell `self`, which PHP reads as this class:
 * the declaring class, the closure's scope class, and the class Macroable binds a macro to.
 */
class SelfTypedHelpers extends Model
{
    use Macroable;
    use PairsWithSelf;

    protected $table = 'posts';

    /** Pair this model with another of its class. */
    public function pairedWith(self $mate): void {}

    /** A getter typed `self`, as an accessor's closure may be. */
    public function selfGetter(): Closure
    {
        return fn (): self => $this;
    }
}
