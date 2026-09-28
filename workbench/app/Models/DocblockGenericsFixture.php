<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Workbench\App\Models\Concerns\AggregatesChildren;

/**
 * Docblock-engine fixtures: every accessor's type lives only in its docblock.
 *
 * @phpstan-type FlagValue bool|int|string
 *
 * @property-read int|null $children_total
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $child_rows
 */
class DocblockGenericsFixture extends Model
{
    /** @use AggregatesChildren<Comment> */
    use AggregatesChildren;

    protected $table = 'docblock_generics_fixtures';

    public function childRows(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /** @return Attribute<?FlagValue, never> */
    protected function flagDefault(): Attribute
    {
        return Attribute::get(fn () => $this->attributes['flag_default'] ?? null);
    }

    /** @return Attribute<Collection<int, User&object{pivot: TaskAssignment}>, never> */
    protected function assignedUsers(): Attribute
    {
        return Attribute::get(fn (): Collection => new Collection);
    }

    /** @return Attribute<array<non-empty-string, bool>, never> */
    protected function abilityMap(): Attribute
    {
        return Attribute::get(fn (): array => []);
    }
}
