<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Workbench\App\ValueObjects\DocblockReads\Concerns\ListsKidNames;

/**
 * CategoryBranch's twin that a subclass may extend, so a `self` inside a generic `@var`, in its trait or its own body,
 * may stand for any subclass and names no element.
 */
class OpenCategoryBranch extends Model
{
    use ListsKidNames;

    protected $table = 'categories';

    protected $guarded = [];

    /** @return HasMany<OpenCategoryBranch, $this> */
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
     * The first kid's name, through `self` inside the `@var` in the class's own body.
     */
    public function ownSelfFirstKidName()
    {
        /** @var Collection<int, self> $kids */
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
}
