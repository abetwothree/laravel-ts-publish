<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model with excluded mutator and relation via #[TsExclude].
 */
class ExcludableModel extends Model
{
    protected $table = 'users';

    /** Included mutator — should appear in TS output */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => strtoupper($this->name ?? ''),
        );
    }

    /** Excluded mutator — should NOT appear in TS output */
    #[TsExclude]
    protected function secretToken(): Attribute
    {
        return Attribute::make(
            get: fn (): string => 'hidden-token',
        );
    }

    /** Included relation — should appear in TS output */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    /** Excluded relation — should NOT appear in TS output */
    #[TsExclude]
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    /** Excluded old-style mutator — should NOT appear in TS output */
    #[TsExclude]
    public function getLegacyTokenAttribute(): string
    {
        return 'old-style-hidden';
    }
}
