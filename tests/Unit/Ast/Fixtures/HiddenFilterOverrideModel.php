<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Casts\MenuSettings;
use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

/**
 * A model whose only() and except() overrides return the model itself, which hides two columns and an appended
 * accessor, casts one column to an enum and one to a class, and appends one accessor typed as a string and one as an
 * enum. Its serialized object leaves the hidden names and the accessor it does not append out, and names a token in
 * the cast ones and the enum one.
 */
final class HiddenFilterOverrideModel extends Model
{
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token', 'secret'];

    protected $appends = ['badge', 'rank', 'secret'];

    protected $casts = ['role' => Role::class, 'options' => MenuSettings::class];

    /** @param  array<int, string>|string  $attributes */
    public function only($attributes): static
    {
        return $this;
    }

    /** @param  array<int, string>|string  $attributes */
    public function except($attributes): static
    {
        return $this;
    }

    /** @return BelongsTo<HiddenFilterOverrideModel, $this> */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id');
    }

    /** @return HasMany<HiddenFilterOverrideModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'id');
    }

    /** @return Attribute<HiddenFilterOverrideModel|User|null, never> */
    protected function counterpart(): Attribute
    {
        return Attribute::get(fn () => null);
    }

    protected function badge(): Attribute
    {
        return Attribute::get(fn (): string => 'badge');
    }

    protected function rank(): Attribute
    {
        return Attribute::get(fn (): Role => Role::Admin);
    }

    protected function secret(): Attribute
    {
        return Attribute::get(fn (): string => 'secret');
    }

    protected function nick(): Attribute
    {
        return Attribute::get(fn (): string => 'nick');
    }
}
