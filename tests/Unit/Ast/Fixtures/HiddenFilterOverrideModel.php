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
 * A model whose only() and except() overrides return the model itself, with two hidden columns, a hidden append, an
 * enum and a class cast, and a string and an enum append. Its serialized object leaves out the hidden names and the
 * accessor it does not append, and names a token in the cast ones and the enum one.
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

    /** An appended accessor typed as a string. */
    protected function badge(): Attribute
    {
        return Attribute::get(fn (): string => 'badge');
    }

    /** An appended accessor typed as an enum. */
    protected function rank(): Attribute
    {
        return Attribute::get(fn (): Role => Role::Admin);
    }

    /** An appended accessor that `$hidden` hides. */
    protected function secret(): Attribute
    {
        return Attribute::get(fn (): string => 'secret');
    }

    /** An accessor the model does not append. */
    protected function nick(): Attribute
    {
        return Attribute::get(fn (): string => 'nick');
    }
}
