<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\TreeCategoryResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\TreeCategory;

/**
 * What Laravel sends for TreeCategoryResource over a saved root category with every relation it reads loaded.
 *
 * @return array<string, mixed>
 */
function whenLoadedCollectionValueSends(): array
{
    $root = TreeCategory::create(['name' => 'Root', 'slug' => 'root']);
    $resource = new TreeCategoryResource(TreeCategory::with(['ancestors', 'children', 'parent'])->findOrFail($root->id));

    /** @var array{data: array<string, mixed>} $sent */
    $sent = json_decode((string) $resource->response(request())->getContent(), true);

    return $sent['data'];
}

// A loaded to-many relation is a collection, so a value that collects it is a list, even where the engine cannot read
// the relation. A nullable to-one relation loads as null, which whenLoaded() returns before it calls the value.
it('adds no null arm to a whenLoaded() value that collects the loaded relation', function () {
    expect(publishedResourceKeyTypes(TreeCategoryResource::class, TreeCategory::class))->toBe([
        'ancestors?' => 'CategoryResource[]',
        'ancestors_closure?' => 'CategoryResource[]',
        'ancestors_self?' => 'TreeCategoryResource[]',
        'ancestors_bare?' => 'unknown',
        'children?' => 'CategoryResource[]',
        'parent_wrap?' => 'CategoryResource | null',
        'parent_collected?' => 'CategoryResource[] | null',
    ])
        ->and(whenLoadedCollectionValueSends())->toBe([
            'ancestors' => [],
            'ancestors_closure' => [],
            'ancestors_self' => [],
            'ancestors_bare' => [],
            'children' => [],
            'parent_wrap' => null,
            'parent_collected' => null,
        ]);
});
