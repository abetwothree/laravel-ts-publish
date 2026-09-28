<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TChild of Model
 */
trait GenericChildrenTrait
{
    /** @return EloquentCollection<int, TChild> */
    public function children()
    {
        return new EloquentCollection;
    }
}
