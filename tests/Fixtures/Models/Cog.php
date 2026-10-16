<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InheritedBodyParentCollection;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;

/** A model whose #[UseResource] names a resource collection, which `toResourceCollection()` lists. */
#[UseResource(InheritedBodyParentCollection::class)]
class Cog extends Model {}
