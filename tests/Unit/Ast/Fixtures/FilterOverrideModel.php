<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\User;

/** A model whose only() and except() overrides declare their returns, read as itself, a relation and a map proxy. */
final class FilterOverrideModel extends Model
{
    protected $table = 'posts';

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): string
    {
        return '';
    }

    /** @param  array<int, string>|string  $attributes */
    public function except($attributes): int
    {
        return 0;
    }

    /** @return BelongsTo<FilterOverrideModel, $this> */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'user_id');
    }

    /** @return HasMany<FilterOverrideModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'user_id');
    }

    /** @return Attribute<FilterOverrideModel|User|null, never> */
    protected function counterpart(): Attribute
    {
        return Attribute::get(fn () => null);
    }
}
