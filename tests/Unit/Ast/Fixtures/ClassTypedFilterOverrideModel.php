<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\User;

/**
 * A model whose only() override returns the model or null and whose except() override returns an enum, so both
 * declared returns name a token, reached as itself, a relation, a map proxy and one arm of a multi-model accessor.
 */
final class ClassTypedFilterOverrideModel extends Model
{
    protected $table = 'tags';

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): ?static
    {
        return $this;
    }

    /** @param  array<int, string>|string  $attributes */
    public function except($attributes): Priority
    {
        return Priority::Low;
    }

    /** @return BelongsTo<ClassTypedFilterOverrideModel, $this> */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id');
    }

    /** @return HasMany<ClassTypedFilterOverrideModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'id');
    }

    /**
     * Each filter beside the key, read as a method body.
     *
     * @return array<string, mixed>
     */
    public function filterFields(): array
    {
        return [
            'own' => $this->only(['id', 'name']),
            'twin' => $this->twin?->only(['id']),
            'rest' => $this->except(['id']),
            'id' => $this->id,
        ];
    }

    /** @return Attribute<ClassTypedFilterOverrideModel|User|null, never> */
    protected function counterpart(): Attribute
    {
        return Attribute::get(fn () => null);
    }
}
