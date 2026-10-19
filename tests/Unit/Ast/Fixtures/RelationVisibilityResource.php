<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Declares no toArray() over a model whose `$visible` lists one column and one relation.
 *
 * @mixin RelationVisibilityUser
 */
final class RelationVisibilityResource extends JsonResource {}
