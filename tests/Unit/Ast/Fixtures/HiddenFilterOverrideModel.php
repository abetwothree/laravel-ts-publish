<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Casts\MenuSettings;
use Workbench\App\Enums\Role;

/**
 * A model whose only() and except() overrides return the model itself, which hides two columns and casts one to an enum
 * and one to a class, so its serialized object leaves the hidden columns out and names a token in the cast ones.
 */
final class HiddenFilterOverrideModel extends Model
{
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];

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

    /** @return HasMany<HiddenFilterOverrideModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'id');
    }
}
