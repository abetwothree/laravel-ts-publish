<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;

/** Casts a resource's `_tag` signature optional, which a signature cannot carry. */
#[TsCasts(['[key: `${string}_tag`]' => ['type' => 'string', 'optional' => true]])]
final class OptionalSignatureCastModel extends Model
{
    protected $table = 'posts';
}
