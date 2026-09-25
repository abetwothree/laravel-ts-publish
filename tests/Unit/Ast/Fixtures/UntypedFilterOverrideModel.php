<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\User;

/**
 * A model whose only() and except() overrides keep Model's behaviour and declare no return, reached as itself, a
 * relation, a map proxy and one arm of a multi-model accessor.
 */
final class UntypedFilterOverrideModel extends Model
{
    protected $table = 'posts';

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes)
    {
        return parent::only($attributes);
    }

    /** @param  array<int, string>|string  $attributes */
    public function except($attributes)
    {
        return parent::except($attributes);
    }

    /** @return BelongsTo<UntypedFilterOverrideModel, $this> */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'user_id');
    }

    /** @return HasMany<UntypedFilterOverrideModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'user_id');
    }

    /** @return Attribute<UntypedFilterOverrideModel|User|null, never> */
    protected function counterpart(): Attribute
    {
        return Attribute::get(fn () => null);
    }
}
