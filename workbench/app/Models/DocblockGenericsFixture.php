<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Docblock-engine fixtures: every accessor's type lives only in its docblock.
 *
 * @phpstan-type FlagValue bool|int|string
 */
class DocblockGenericsFixture extends Model
{
    protected $table = 'docblock_generics_fixtures';

    /** @return Attribute<?FlagValue, never> */
    protected function flagDefault(): Attribute
    {
        return Attribute::get(fn () => $this->attributes['flag_default'] ?? null);
    }
}
