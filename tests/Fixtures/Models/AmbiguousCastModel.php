<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;

/** A test-only model whose one cast key spells both of a resource's backslash signatures' names. */
#[TsCasts(['[key: `${string}\\\\r`]' => 'string'])]
class AmbiguousCastModel extends Model
{
    protected $table = 'posts';
}
