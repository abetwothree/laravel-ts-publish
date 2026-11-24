<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Workbench\App\ValueObjects\DocblockReads\Concerns\ListsKidNames;

/**
 * A final model over the existing categories table, kept out of app/Models so it publishes no model of its own: a
 * generic `@var` names an element only when it is a model, so a value object cannot hold these probes (GH-134).
 */
final class CategoryBranch extends Model
{
    use ListsKidNames;

    protected $table = 'categories';

    protected $guarded = [];

    /** @return HasMany<CategoryBranch, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The kids' names re-indexed into a list, through `self` inside the `@var` in the class's own body.
     */
    public function ownSelfKidNameList()
    {
        /** @var Collection<int, self> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->map(fn ($kid) => $kid->name)->values()->all();
    }

    /**
     * The kids' names re-indexed into a list, through the class named inside the `@var` in the class's own body.
     */
    public function ownNamedKidNameList()
    {
        /** @var Collection<int, CategoryBranch> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->map(fn ($kid) => $kid->name)->values()->all();
    }

    /**
     * The first kid's name, through `self` inside the `@var` in the class's own body.
     */
    public function ownSelfFirstKidName()
    {
        /** @var Collection<int, self> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->first()?->name;
    }

    /**
     * The first kid's name, through the class named inside the `@var` in the class's own body.
     */
    public function ownNamedFirstKidName()
    {
        /** @var Collection<int, CategoryBranch> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->first()?->name;
    }

    /**
     * The kids' sort orders, through `self` inside the `@var` in the class's own body.
     */
    public function ownSelfKidSortOrders()
    {
        /** @var Collection<int, self> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->map(fn ($kid) => $kid->sort_order);
    }

    /**
     * The kids' sort orders, through the class named inside the `@var` in the class's own body.
     */
    public function ownNamedKidSortOrders()
    {
        /** @var Collection<int, CategoryBranch> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->map(fn ($kid) => $kid->sort_order);
    }
}
