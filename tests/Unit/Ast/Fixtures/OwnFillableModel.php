<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model that redeclares `$fillable` itself, so its declaring class is this class rather than
 * `Illuminate\…\Model` — the only way the `Model::class` framework-base exclusion can be reached.
 */
final class OwnFillableModel extends Model
{
    protected $table = 'posts';

    protected $fillable = ['title'];
}
