<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TraitBodies\ReadsDeclaredPost;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * Takes its toArray() from a trait in another namespace, whose `@var` resolves against the trait's own imports.
 *
 * @mixin Tag
 */
final class DeclaredVarTraitResource extends JsonResource
{
    use ReadsDeclaredPost;
}
