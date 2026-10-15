<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PreserveKeysFlatCollection;
use Workbench\App\Http\Resources\SupplierCollection;
use Workbench\App\Http\Resources\SupplierSummaryCollection;
use Workbench\App\Http\Resources\SupplierSummaryResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Models\Post;

describe('ResourceCollections', function () {
    test('collectedResource() reads the $collects property, then the naming convention', function (string $collection, string $collected) {
        expect(ResourceCollections::collectedResource($collection))->toBe($collected);
    })->with([
        'the $collects property' => [SupplierCollection::class, SupplierSummaryResource::class],
        'FooCollection collects FooResource' => [SupplierSummaryCollection::class, SupplierSummaryResource::class],
    ]);

    test('preservesKeys() reads the class\'s $preserveKeys', function (string $collection, bool $preserves) {
        expect(ResourceCollections::preservesKeys(new ReflectionClass($collection)))->toBe($preserves);
    })->with([
        'keeps its keys' => [PreserveKeysFlatCollection::class, true],
        're-indexes' => [SupplierCollection::class, false],
    ]);

    test('listType() lists the element, or keys it by string when the collection keeps its keys', function (string $collection, string $type) {
        expect(ResourceCollections::listType('R', new ReflectionClass($collection)))->toBe($type);
    })->with([
        'a list' => [SupplierCollection::class, 'R[]'],
        'a keyed record' => [PreserveKeysFlatCollection::class, 'Record<string, R>'],
    ]);

    test('reindexesNumericKeys() holds for an API resource that does not keep its keys', function (string $class, bool $reindexes) {
        expect(ResourceCollections::reindexesNumericKeys(new ReflectionClass($class)))->toBe($reindexes);
    })->with([
        'an API resource' => [PostResource::class, true],
        'a key-keeping collection' => [PreserveKeysFlatCollection::class, false],
        'a model' => [Post::class, false],
    ]);
});

test('a rebased scope carries the collection facts of the class it rebases onto', function () {
    $scope = new AnalysisScope(new ReflectionClass(Post::class), Post::class);

    $scope->rebase(new ReflectionClass(SupplierCollection::class), null);
    $collection = [$scope->collectedResource, $scope->reindexesNumericKeys];

    $scope->rebase(new ReflectionClass(PreserveKeysFlatCollection::class), null);
    $keyed = [$scope->collectedResource, $scope->reindexesNumericKeys];

    $scope->rebase(new ReflectionClass(Post::class), Post::class);

    expect($collection)->toBe([SupplierSummaryResource::class, true])
        ->and($keyed)->toBe([TeamResource::class, false])
        ->and([$scope->collectedResource, $scope->reindexesNumericKeys])->toBe([null, false]);
});
