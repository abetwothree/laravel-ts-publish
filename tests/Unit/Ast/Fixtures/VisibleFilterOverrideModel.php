<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A model whose only() and except() overrides return the model itself or null, which lists three visible columns and
 * hides one of them, so its serialized object holds only the other two.
 */
final class VisibleFilterOverrideModel extends Model
{
    protected $table = 'tags';

    protected $visible = ['id', 'name', 'color'];

    protected $hidden = ['color'];

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
}
