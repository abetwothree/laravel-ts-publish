<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollectionBody;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CastParentCallResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ClassCastStubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CollectionAllCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CollectionReturnCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InheritedBodyParentCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MethodCastStubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NestedSupplierListResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\RenamedStubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ResourceJsonSerializeCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ResourceReturnCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ResourceToArrayCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubAttributeCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubChildOfStubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubCollectsCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubFirstReturnCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubFlatCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubGadgetCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubKeyedCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubLeafInheritCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubLeafOwnCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubLoneSpreadCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubMergeCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubNestingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubPlainVariableCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubPlusCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubRowsCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubSpreadCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubValueCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubVariableCollection;
use Workbench\App\Http\Resources\SupplierCollection;

/** The whole file a class publishes. */
function publishedResourceFile(string $class): string
{
    config()->set('ts-publish.output_to_files', false);

    return resolve(ResourceGenerator::class, ['findable' => $class])->content;
}

/** The interface or alias lines a class publishes, without its imports and docblock. */
function publishedCollectionBody(string $class): string
{
    return implode("\n", array_values(array_filter(
        explode("\n", publishedResourceFile($class)),
        fn (string $line): bool => str_starts_with($line, 'export') || str_starts_with($line, '    '),
    )));
}

/**
 * The import lines a class's file publishes.
 *
 * @return list<string>
 */
function publishedImportLines(string $class): array
{
    return array_values(array_filter(
        explode("\n", publishedResourceFile($class)),
        fn (string $line): bool => str_starts_with($line, 'import'),
    ));
}

$belowLaravel13 = fn (): bool => ! version_compare(app()->version(), '13', '>=');

describe('a collection whose toArray() is the collected list publishes what Laravel wraps it in', function () use ($belowLaravel13) {
    test('the make:resource stub publishes as a body-less collection does', function (string $class, string $expected) {
        expect(publishedCollectionBody($class))->toBe($expected);
    })->with([
        'over $collects' => [StubCollectsCollection::class, "export interface StubCollectsCollection\n    data: StubGadgetResource[];"],
        'over the naming convention' => [StubGadgetCollection::class, "export interface StubGadgetCollection\n    data: StubGadgetResource[];"],
        'under $wrap = null' => [StubFlatCollection::class, 'export type StubFlatCollection = StubGadgetResource[];'],
        'under its own wrap key' => [StubRowsCollection::class, "export interface StubRowsCollection\n    rows: StubGadgetResource[];"],
        'stacked, its own $collects' => [StubLeafOwnCollection::class, "export interface StubLeafOwnCollection\n    data: StubGizmoResource[];"],
        'stacked, the $collects it inherits' => [StubLeafInheritCollection::class, "export interface StubLeafInheritCollection\n    data: StubGadgetResource[];"],
        'body-less over a stub' => [StubChildOfStubCollection::class, "export interface StubChildOfStubCollection\n    data: StubGizmoResource[];"],
        'returning $this->resource' => [ResourceReturnCollection::class, "export interface ResourceReturnCollection\n    data: StubGadgetResource[];"],
        'returning $this->resource->toArray()' => [ResourceToArrayCollection::class, "export interface ResourceToArrayCollection\n    data: StubGadgetResource[];"],
        'returning $this->resource->jsonSerialize()' => [ResourceJsonSerializeCollection::class, "export interface ResourceJsonSerializeCollection\n    data: StubGadgetResource[];"],
        'returning $this->collection' => [CollectionReturnCollection::class, "export interface CollectionReturnCollection\n    data: StubGadgetResource[];"],
        'returning $this->collection->all()' => [CollectionAllCollection::class, "export interface CollectionAllCollection\n    data: StubGadgetResource[];"],
        'with a method #[TsCasts]' => [MethodCastStubCollection::class, "export interface MethodCastStubCollection\n    data: StubGadgetResource[];\n    meta: string;"],
        'with a class #[TsCasts]' => [ClassCastStubCollection::class, "export interface ClassCastStubCollection\n    data: StubGadgetResource[];\n    meta: string;"],
        'renamed by #[TsResource]' => [RenamedStubCollection::class, "export interface RenamedGadgetList\n    data: StubGadgetResource[];"],
        'an unwritten variable' => [StubPlainVariableCollection::class, "export interface StubPlainVariableCollection\n    data: StubGadgetResource[];"],
        'a lone spread' => [StubLoneSpreadCollection::class, "export interface StubLoneSpreadCollection\n    data: StubGadgetResource[];"],
        'the first return of a body the sweep cannot read' => [StubFirstReturnCollection::class, "export interface StubFirstReturnCollection\n    data: StubGadgetResource[];"],
    ]);

    test('the stub reads #[Collects] and #[PreserveKeys] off the publishing class', function (string $class, string $expected) {
        expect(publishedCollectionBody($class))->toBe($expected);
    })->with([
        'over #[Collects]' => [StubAttributeCollection::class, "export interface StubAttributeCollection\n    data: StubGadgetResource[];"],
        'under #[PreserveKeys]' => [StubKeyedCollection::class, "export interface StubKeyedCollection\n    data: Record<string, StubGadgetResource>;"],
    ])->skip($belowLaravel13);
});

describe('a parent::toArray() a collection builds on is the bare collected list', function () {
    test('publishes its numeric keys beside the keys written next to it', function (string $class, string $expected) {
        expect(publishedCollectionBody($class))->toBe($expected);
    })->with([
        'spread into a literal' => [StubSpreadCollection::class, "export interface StubSpreadCollection\n    [key: number]: StubGadgetResource;\n    extra: number;"],
        'a variable with a key written' => [StubVariableCollection::class, "export interface StubVariableCollection\n    [key: number]: StubGadgetResource;\n    extra: number;"],
        'a + operand' => [StubPlusCollection::class, "export interface StubPlusCollection\n    [key: number]: StubGadgetResource;\n    extra: number;"],
        'an array_merge() argument' => [StubMergeCollection::class, "export interface StubMergeCollection\n    [key: number]: StubGadgetResource;\n    extra: number;"],
        'a key\'s value' => [StubValueCollection::class, "export interface StubValueCollection\n    items: StubGadgetResource[];"],
    ]);
});

test('a resource key holding a collection sends its own toArray() keys unless that body is the collected list', function () {
    expect(publishedCollectionBody(StubNestingResource::class))->toBe(implode("\n", [
        'export interface StubNestingResource',
        '    stub_new: StubGadgetResource[];',
        '    stub_make: StubGadgetResource[];',
        '    resource_new: StubGadgetResource[];',
        '    body_new: InheritedBodyParentCollection;',
        '    body_make: InheritedBodyParentCollection;',
    ]));
});

// The token needs the collection's own file, so outside the published set the key keeps the list it collects.
test('an excluded nested collection with a body keeps R[] and imports nothing from it', function (array $excluded, string $type) {
    config()->set('ts-publish.resources.excluded', $excluded);
    PublishedResourceRegistry::register(resolve(ResourcesCollector::class)->collect());

    expect(publishedCollectionBody(NestedSupplierListResource::class))->toBe(implode("\n", [
        'export interface NestedSupplierListResource',
        "    suppliers: {$type};",
        "    suppliers_made: {$type};",
    ]));

    $importsCollection = array_any(publishedImportLines(NestedSupplierListResource::class), fn (string $line): bool => str_contains($line, 'SupplierCollection'));

    expect($importsCollection)->toBe($excluded === []);
})->with([
    'published' => [[], 'SupplierCollection'],
    'excluded' => [[SupplierCollection::class], 'SupplierSummaryResource[]'],
]);

// The method lookup is memoized across a run, so a later build that nests the same collection parses nothing.
test('a nested collection\'s body is a dependency of the build that reads it, on a warm lookup too', function () {
    $body = resolve(ResourceCollectionBody::class);
    $body->sendsOwnShape(InheritedBodyParentCollection::class);

    DependencyRecorder::start();

    try {
        $body->sendsOwnShape(InheritedBodyParentCollection::class);
        $paths = DependencyRecorder::paths();
    } finally {
        DependencyRecorder::stop();
    }

    expect($paths)->toContain((string) new ReflectionClass(InheritedBodyParentCollection::class)->getFileName());
});

test('a method #[TsCasts] on a toArray() that returns parent::toArray() applies', function () {
    expect(publishedCollectionBody(CastParentCallResource::class))->toBe(implode("\n", [
        'export interface CastParentCallResource',
        '    gadget_id: number;',
        '    gadget_name: string;',
        '    extra: string;',
    ]));
});
