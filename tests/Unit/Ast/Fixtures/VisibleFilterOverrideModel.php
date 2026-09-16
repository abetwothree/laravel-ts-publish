<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A model whose only() and except() overrides return the model itself or null, which lists three visible columns and
 * a visible appended accessor, hides one of those columns, and appends an accessor outside `$visible` and one named
 * like a column, so its serialized object holds `id`, `name` and `label`, each once.
 */
final class VisibleFilterOverrideModel extends Model
{
    protected $table = 'tags';

    protected $visible = ['id', 'name', 'color', 'label'];

    protected $hidden = ['color'];

    protected $appends = ['label', 'shade', 'name'];

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): ?self
    {
        return $this;
    }

    /** @param  array<int, string>|string  $attributes */
    public function except($attributes): ?self
    {
        return $this;
    }

    /** @return BelongsTo<VisibleFilterOverrideModel, $this> */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id');
    }

    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => 'label');
    }

    protected function shade(): Attribute
    {
        return Attribute::get(fn (): string => 'shade');
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (string $value): string => ucfirst($value));
    }
}
