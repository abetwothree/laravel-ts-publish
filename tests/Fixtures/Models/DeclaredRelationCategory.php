<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns\HasTreeAncestors;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Ancestors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use LogicException;

/**
 * A test-only model on the `categories` table whose relation methods only their docblocks declare. `ancestors()` is
 * a relation; `lineage()` reads the parent, which a blank instance does not have; `labels()` names no relation class
 * and `ancestorsUpTo()` takes a parameter, so reading the model's relations calls neither.
 */
class DeclaredRelationCategory extends Model
{
    use HasTreeAncestors;

    protected $table = 'categories';

    /**
     * The category this one sits under.
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The ancestors that share the parent's slug.
     *
     * @return Ancestors<static, static>
     */
    public function lineage()
    {
        return $this->newAncestors($this->newQuery(), $this, 'parent_id', 'id')->where('slug', $this->parent->slug);
    }

    /**
     * Not a relation, so reading the model's relations must never call it.
     *
     * @return Collection<int, string>
     */
    public function labels()
    {
        throw new LogicException('Only a relation read should reach a relation method.');
    }

    /**
     * The ancestors up to a depth, which takes a parameter, so reading the model's relations must never call it.
     *
     * @return Ancestors<static, static>
     */
    public function ancestorsUpTo(int $depth)
    {
        throw new LogicException('Only a parameterless method is read as a relation.');
    }
}
