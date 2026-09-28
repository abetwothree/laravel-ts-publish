<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Casts\MenuSettings;
use Workbench\App\Models\User;

/** A model with no filter override, one column cast to a class and a relation to another model, both naming a token. */
final class TokenColumnModel extends Model
{
    protected $table = 'tags';

    protected $casts = ['color' => MenuSettings::class];

    /** @return BelongsTo<TokenColumnModel, $this> */
    public function twin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id');
    }

    /** @return HasMany<TokenColumnModel, $this> */
    public function twins(): HasMany
    {
        return $this->hasMany(self::class, 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }
}
