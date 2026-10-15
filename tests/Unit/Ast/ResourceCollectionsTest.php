<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38DialLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38NamedLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Gauge;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GaugeCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GaugeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InheritedBodyLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InheritedBodyParentCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KnobCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KnobResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StackedUnnamedCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubGizmoResource;
use Workbench\App\Http\Resources\MiscCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PreserveKeysFlatCollection;
use Workbench\App\Http\Resources\SupplierCollection;
use Workbench\App\Http\Resources\SupplierSummaryCollection;
use Workbench\App\Http\Resources\SupplierSummaryResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Supplier;

describe('ResourceCollections', function () {
    test('collectedResource() reads the $collects property, then the naming convention', function (string $collection, string $collected) {
        expect(ResourceCollections::collectedResource($collection))->toBe($collected);
    })->with([
        'the $collects property' => [SupplierCollection::class, SupplierSummaryResource::class],
        'FooCollection collects FooResource' => [SupplierSummaryCollection::class, SupplierSummaryResource::class],
        'FooCollection collects a bare Foo before FooResource' => [GaugeCollection::class, Gauge::class],
    ]);

    test('collectedResource() collects nothing when a populated registry leaves the convention\'s pick unpublished', function () {
        PublishedResourceRegistry::register([GaugeResource::class]);

        expect(ResourceCollections::collectedResource(GaugeCollection::class))->toBeNull();
    });

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

    test('collectedElement() is the API resource collected, else the model the nearest ancestor\'s resource wraps', function (string $collection, ?array $element) {
        expect(ResourceCollections::collectedElement(new ReflectionClass($collection)))->toBe($element);
    })->with([
        'its own pick' => [SupplierCollection::class, ['fqcn' => SupplierSummaryResource::class, 'model' => false]],
        'a stacked class naming none' => [StackedUnnamedCollection::class, ['fqcn' => Supplier::class, 'model' => true]],
        'a pick that is no API resource' => [F38DialLeafCollection::class, null],
        'naming none, with no collection above it' => [MiscCollection::class, null],
    ]);

    test('collectedElement() keeps the ancestor\'s resource while its model is unpublished', function () {
        PublishedModelRegistry::register([Post::class]);

        expect(ResourceCollections::collectedElement(new ReflectionClass(F38NamedLeafCollection::class)))
            ->toBe(['fqcn' => StubGizmoResource::class, 'model' => false]);
    });

    test('collectedElement() declines a stacked class whose own convention pick the run does not publish', function () {
        PublishedResourceRegistry::register([StubGizmoResource::class]);

        expect(class_exists(KnobResource::class))->toBeTrue()
            ->and(ResourceCollections::collectedElement(new ReflectionClass(KnobCollection::class)))->toBeNull();
    });

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

test('a scope seeded from a runtime class carries its collection facts, not its subject\'s', function () {
    $scope = new AnalysisScope(new ReflectionClass(InheritedBodyParentCollection::class));

    $scope->seedCollectionFacts(new ReflectionClass(InheritedBodyLeafCollection::class));

    expect([$scope->collectedResource, $scope->collectedElement, $scope->reindexesNumericKeys])
        ->toBe([StubGizmoResource::class, ['fqcn' => StubGizmoResource::class, 'model' => false], true]);
});
