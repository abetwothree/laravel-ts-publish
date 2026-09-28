<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;

/** Appends an accessor whose getter filters, so a spread of the model's toArray() reads that getter. */
final class AppendingFilteringAccessorModel extends Model
{
    protected $table = 'posts';

    /** @var list<string> */
    protected $appends = ['author_picks'];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Filters a single relation, keeping an enum column. */
    protected function authorPicks(): Attribute
    {
        return Attribute::get(fn () => ['v' => $this->author?->only(['id', 'role']), 'id' => $this->id]);
    }
}
