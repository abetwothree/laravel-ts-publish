<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Priority;

/**
 * A test-only model on the `grades` table, which reads no enum of its own, appending a shape that holds a wrap and a
 * wrap alone (GH-96).
 */
class WrapAccessorGrade extends Model
{
    protected $table = 'grades';

    protected $appends = ['shape', 'wrap'];

    /** A shape holding a wrap beside an int. */
    protected function shape(): Attribute
    {
        return Attribute::get(fn () => ['a' => EnumResource::make(Priority::High), 'b' => 1]);
    }

    /** A wrap alone. */
    protected function wrap(): Attribute
    {
        return Attribute::get(fn () => EnumResource::make(Priority::High));
    }
}
