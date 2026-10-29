<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A to-many relation a package builds through its own helper, as an adjacency-list package does.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends HasMany<TRelatedModel, TDeclaringModel>
 */
class Ancestors extends HasMany {}
