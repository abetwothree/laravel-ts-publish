<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Exercises the ModelTransformer unknown-type fallback paths
 * using untyped SQLite columns.
 */
class UntypedColumn extends Model
{
    protected $table = 'untyped_columns';

    public $timestamps = false;

    protected $fillable = [
        'accessor_col',
        'cast_col',
        'nullable_accessor_col',
    ];

    /**
     * Accessor with no return type on the getter closure — resolves to unknown,
     * exercises the 'attribute'/'accessor' match arm
     */
    protected function accessorCol(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
        );
    }

    /**
     * Accessor with no return type on an untyped nullable column — exercises
     * the nullable fallback (appends ' | null').
     */
    protected function nullableAccessorCol(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
        );
    }
}
