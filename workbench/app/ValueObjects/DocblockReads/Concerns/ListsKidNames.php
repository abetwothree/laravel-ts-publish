<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads\Concerns;

use Illuminate\Support\Collection;
use Workbench\App\ValueObjects\DocblockReads\CategoryBranch;

/**
 * Reads the using branch's kids through a generic `@var` that names `self` or the class itself (GH-134).
 */
trait ListsKidNames
{
    /**
     * The kids' names, through `self` inside the `@var`.
     */
    public function selfKidNames()
    {
        /** @var Collection<int, self> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->map(fn ($kid) => $kid->name)->all();
    }

    /**
     * The kids' names, through the class named inside the `@var`.
     */
    public function namedKidNames()
    {
        /** @var Collection<int, CategoryBranch> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->map(fn ($kid) => $kid->name)->all();
    }

    /**
     * The first kid's name, through `self` inside the `@var`.
     */
    public function selfFirstKidName()
    {
        /** @var Collection<int, self> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->first()?->name;
    }

    /**
     * The first kid's name, through the class named inside the `@var`.
     */
    public function namedFirstKidName()
    {
        /** @var Collection<int, CategoryBranch> $kids */
        $kids = $this->getRelationValue('children');

        return $kids->first()?->name;
    }
}
