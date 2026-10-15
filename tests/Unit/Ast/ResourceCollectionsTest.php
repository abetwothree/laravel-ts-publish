<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38BodyLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38DialLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38NamedLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Gauge;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GaugeCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GaugeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InheritedBodyLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InheritedBodyParentCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KnobCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KnobLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KnobResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LeverLeafCollection;
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
    test('collectedElement() reads the $collects property, then the naming convention', function (string $collection, string $collected) {
        expect(ResourceCollections::collectedElement(new ReflectionClass($collection)))->toBe(['fqcn' => $collected, 'model' => false]);
    })->with([
        'the $collects property' => [SupplierCollection::class, SupplierSummaryResource::class],
        'FooCollection collects FooResource' => [SupplierSummaryCollection::class, SupplierSummaryResource::class],
        'FooCollection collects a bare Foo before FooResource' => [GaugeCollection::class, Gauge::class],
    ]);

    test('collectedElement() collects nothing when a populated registry leaves the convention\'s pick unpublished', function () {
        PublishedResourceRegistry::register([GaugeResource::class]);

        expect(ResourceCollections::collectedElement(new ReflectionClass(GaugeCollection::class)))->toBeNull();
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

    // Laravel sends the raw models whatever an ancestor picks, so only the nearest pick can name them.
    test('collectedElement() stops at the nearest ancestor with a pick, and warns of none of theirs', function (string $leaf, ?array $element) {
        PublishedResourceRegistry::register([StubGizmoResource::class]);

        expect(ResourceCollections::collectedElement(new ReflectionClass($leaf)))->toBe($element)
            ->and(AnalysisWarnings::all())->toBe([]);
    })->with([
        'an unpublished API resource naming no model' => [KnobLeafCollection::class, null],
        'a class that is no API resource' => [LeverLeafCollection::class, null],
        'an unpublished API resource naming a published model' => [F38BodyLeafCollection::class, ['fqcn' => Supplier::class, 'model' => true]],
    ]);

    test('listValue() lists the element on its import channel, keyed when the collection keeps its keys', function (?array $element, bool $preservesKeys, array $value) {
        expect(ResourceCollections::listValue($element, $preservesKeys))->toBe($value);
    })->with([
        'an API resource' => [['fqcn' => PostResource::class, 'model' => false], false, ['type' => 'PostResource[]', 'optional' => false, 'resourceFqcn' => PostResource::class]],
        'a model' => [['fqcn' => Supplier::class, 'model' => true], false, ['type' => 'Supplier[]', 'optional' => false, 'modelFqcn' => Supplier::class]],
        'kept keys' => [['fqcn' => PostResource::class, 'model' => false], true, ['type' => 'Record<string, PostResource>', 'optional' => false, 'resourceFqcn' => PostResource::class]],
        'no element' => [null, false, ['type' => 'unknown[]', 'optional' => false]],
        'no element, kept keys' => [null, true, ['type' => 'Record<string, unknown>', 'optional' => false]],
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
    $collection = [$scope->collectedElement, $scope->reindexesNumericKeys];

    $scope->rebase(new ReflectionClass(PreserveKeysFlatCollection::class), null);
    $keyed = [$scope->collectedElement, $scope->reindexesNumericKeys];

    $scope->rebase(new ReflectionClass(Post::class), Post::class);

    expect($collection)->toBe([['fqcn' => SupplierSummaryResource::class, 'model' => false], true])
        ->and($keyed)->toBe([['fqcn' => TeamResource::class, 'model' => false], false])
        ->and([$scope->collectedElement, $scope->reindexesNumericKeys])->toBe([null, false]);
});

test('a scope seeded from a runtime class carries its collection facts, not its subject\'s', function () {
    $scope = new AnalysisScope(new ReflectionClass(InheritedBodyParentCollection::class));

    $scope->seedCollectionFacts(new ReflectionClass(InheritedBodyLeafCollection::class));

    expect([$scope->collectedElement, $scope->reindexesNumericKeys])
        ->toBe([['fqcn' => StubGizmoResource::class, 'model' => false], true]);
});

// `$this->resource` holds the items `$this->collection` lists, so both read the one element the scope seeds.
test('a collection\'s $this->resource carries the element its scope seeds', function () {
    $scope = new AnalysisScope(new ReflectionClass(StackedUnnamedCollection::class));
    $scope->seedCollectionFacts(new ReflectionClass(StackedUnnamedCollection::class));

    expect($scope->proxyReceiver()?->collectedElement)->toBe(['fqcn' => Supplier::class, 'model' => true]);
});
