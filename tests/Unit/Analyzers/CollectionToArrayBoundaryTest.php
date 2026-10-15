<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollectionBody;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaStackedCollectionController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CastParentCallResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ClassCastStubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CollectionAllCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CollectionReturnCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Dial;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38AttrLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38AttrRowsLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38BodyLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38DialLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38FlatLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38FlatNamedLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38KeyedLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38KeyedParentLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38ListLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38NamedLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38PropKeyedLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38SpreadLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38ValueLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InheritedBodyLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InheritedBodyParentCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KeyedSortedTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KnobCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KnobLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Lever;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LeverCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LeverLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MethodCastStubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NestedSupplierListResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\RawFlatCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\RawKeyedCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\RenamedStubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ResourceJsonSerializeCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ResourceReturnCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ResourceToArrayCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SortedTagsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StackedLeafNestingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubAttributeCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubAttrLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubChildOfStubCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubCollectsCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubFirstReturnCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubFlatCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubGadgetCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubGizmoResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubKeyedCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubLeafInheritCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubLeafOwnCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubLoneSpreadCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubMergeCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubNamedLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubNestingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubPlainVariableCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubPlusCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubRowsCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubSpreadCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubSprocketCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubSprocketLeafCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubValueCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubVariableCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Cog;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Ratchet;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Sprocket;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Http\Resources\SupplierCollection;
use Workbench\App\Models\Post;
use Workbench\App\Models\Supplier;

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

/**
 * What an argument-less `$rows->toResourceCollection()` publishes over a collection of the given model.
 *
 * @param  class-string<Model>  $model
 */
function guessedResourceCollectionType(string $model): string
{
    $subject = new ReflectionClass(StubNestingResource::class);
    $scope = new AnalysisScope($subject, Supplier::class);
    $scope->varCollectionBindings['rows'] = ['type' => class_basename($model).'[]', 'modelFqcn' => $model];
    $expr = new AstParser()->parseSource('<?php $rows->toResourceCollection();')[0]->expr;

    return new ResourceAstAnalyzer($subject, Supplier::class, scope: $scope)->resolve($expr)['type'];
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

describe('a stacked collection collects by its own class, as Laravel does', function () use ($belowLaravel13) {
    test('a leaf naming no resource sends the raw models of its parent\'s resource', function (string $class, string $expected) {
        expect(publishedCollectionBody($class))->toBe($expected);
    })->with([
        'over #[Collects]' => [F38AttrLeafCollection::class, "export interface F38AttrLeafCollection\n    data: Supplier[];"],
        'under its own wrap key' => [F38AttrRowsLeafCollection::class, "export interface F38AttrRowsLeafCollection\n    rows: Supplier[];"],
        'over a $wrap = null parent' => [F38FlatLeafCollection::class, 'export type F38FlatLeafCollection = Supplier[];'],
        'over a #[PreserveKeys] parent' => [F38KeyedParentLeafCollection::class, "export interface F38KeyedParentLeafCollection\n    data: Supplier[];"],
        'under its own #[PreserveKeys]' => [F38KeyedLeafCollection::class, "export interface F38KeyedLeafCollection\n    data: Record<string, Supplier>;"],
        'under its own $preserveKeys' => [F38PropKeyedLeafCollection::class, "export interface F38PropKeyedLeafCollection\n    data: Record<string, Supplier>;"],
        'the stub over #[Collects]' => [StubAttrLeafCollection::class, "export interface StubAttrLeafCollection\n    data: Supplier[];"],
    ])->skip($belowLaravel13);

    test('a leaf naming no resource over the naming convention sends raw models', function () {
        expect(publishedCollectionBody(F38NamedLeafCollection::class))->toBe("export interface F38NamedLeafCollection\n    data: Supplier[];")
            ->and(publishedCollectionBody(StubNamedLeafCollection::class))->toBe("export interface StubNamedLeafCollection\n    data: Supplier[];")
            ->and(publishedImportLines(F38NamedLeafCollection::class))->toBe(["import type { Supplier } from '../../../../../../workbench/app/models';"]);
    });

    test('a leaf naming no resource reads its parent\'s toArray() as the raw models, spread or as a value', function (string $class, string $expected) {
        expect(publishedCollectionBody($class))->toBe($expected);
    })->with([
        'spread into a literal' => [F38SpreadLeafCollection::class, "export interface F38SpreadLeafCollection\n    [key: number]: Supplier;\n    extra: number;"],
        'a key\'s value' => [F38ValueLeafCollection::class, "export interface F38ValueLeafCollection\n    items: Supplier[];"],
    ]);

    test('a leaf inheriting its parent\'s own toArray() reads $this->collection as its own resource', function () {
        expect(publishedCollectionBody(InheritedBodyLeafCollection::class))
            ->toBe("export interface InheritedBodyLeafCollection\n    data: StubGizmoResource[];\n    count: number;");
    });

    test('a leaf naming nothing reads $this->collection in its parent\'s body as the same element the delegated path publishes', function () {
        expect(publishedCollectionBody(F38BodyLeafCollection::class))
            ->toBe("export interface F38BodyLeafCollection\n    data: Supplier[];\n    count: number;")
            ->and(publishedCollectionBody(F38ListLeafCollection::class))
            ->toBe("export interface F38ListLeafCollection\n    data: Supplier[];");
    });

    test('a leaf whose parent resource\'s model is unpublished keeps the parent\'s resource', function (string $class, string $expected) {
        PublishedModelRegistry::register([Post::class]);

        expect(publishedCollectionBody($class))->toBe($expected);
    })->with([
        'the delegated list' => [F38NamedLeafCollection::class, "export interface F38NamedLeafCollection\n    data: StubGizmoResource[];"],
        '$this->collection in its parent\'s body' => [F38BodyLeafCollection::class, "export interface F38BodyLeafCollection\n    data: StubWidgetResource[];\n    count: number;"],
    ]);

    test('a $wrap = null leaf naming no resource lists the raw models, and over a paginator is their paginator', function () {
        $pageType = new InertiaPageAnalyzer()->analyze(['uses' => InertiaStackedCollectionController::class.'@flatPaginated'])['pageType'] ?? null;

        expect(publishedCollectionBody(F38FlatNamedLeafCollection::class))->toBe('export type F38FlatNamedLeafCollection = Supplier[];')
            ->and(publishedImportLines(F38FlatNamedLeafCollection::class))->toBe(["import type { Supplier } from '../../../../../../workbench/app/models';"])
            ->and($pageType)->toBe('Inertia.SharedData & { suppliers: JsonResourcePaginator<Supplier> }');
    });

    // Laravel throws for the leaf's own pick, so the parent's element would type a response that never comes.
    test('a leaf whose own collects() names no API resource is declined, not given its parent\'s element', function (string $class, string $collected) {
        expect(publishedCollectionBody($class))->toBe('export interface '.class_basename($class))
            ->and(AnalysisWarnings::all())->toContain([
                'subject' => $class,
                'message' => "The collected class {$collected} is not an API resource, so Laravel throws a LogicException; collect an API resource.",
            ]);
    })->with([
        'by its own $collects' => [F38DialLeafCollection::class, Dial::class],
        'by its own name' => [LeverCollection::class, Lever::class],
    ]);

    // Laravel maps every item into the pick it finds, so neither the parent's resource nor its model types them.
    test('a leaf whose own convention pick is an API resource the run does not publish is declined', function () {
        PublishedResourceRegistry::register([StubGizmoResource::class]);

        expect(publishedCollectionBody(KnobCollection::class))->toBe('export interface KnobCollection');
    });

    // collects() picks nothing, so Laravel sends the raw items it was given, under the wrap key as ever.
    test('a collection whose collects() picks nothing and no ancestor names an element sends an unknown list', function (string $class, string $expected) {
        PublishedResourceRegistry::register([StubGizmoResource::class]);

        expect(publishedCollectionBody($class))->toBe($expected);
    })->with([
        'over an unpublished ancestor pick naming no model' => [KnobLeafCollection::class, "export interface KnobLeafCollection\n    data: unknown[];"],
        'over an ancestor pick that is no API resource' => [LeverLeafCollection::class, "export interface LeverLeafCollection\n    data: unknown[];"],
        'keeping its keys' => [RawKeyedCollection::class, "export interface RawKeyedCollection\n    data: Record<string, unknown>;"],
        'with no wrap key' => [RawFlatCollection::class, 'export type RawFlatCollection = unknown[];'],
    ]);

    // Laravel sets `$this->resource` to the items `$this->collection` lists, or the paginator holding them.
    test('a leaf naming no resource reads $this->resource in its parent\'s body as the raw models, in every holder', function (string $class, string $element) {
        expect(publishedCollectionBody($class))->toBe(implode("\n", [
            'export interface '.class_basename($class),
            "    page: {$element}[] | LengthAwarePaginator<{$element}> | SimplePaginator<{$element}> | CursorPaginator<{$element}>;",
            "    first: {$element} | null;",
        ]));
    })->with([
        'the parent' => [StubSprocketCollection::class, 'StubSprocketResource'],
        'the leaf' => [StubSprocketLeafCollection::class, 'Supplier'],
    ]);

    test('a leaf naming no resource reads $this->resource with the raw models\' import', function () {
        expect(publishedImportLines(StubSprocketLeafCollection::class))
            ->toContain("import type { Supplier } from '../../../../../../workbench/app/models';");
    });

    // Laravel nests a body-less collection through jsonSerialize(), which sends the collected list without its wrap.
    test('a resource key holding a leaf naming no resource sends the raw models bare', function () {
        expect(publishedCollectionBody(StackedLeafNestingResource::class))->toBe(implode("\n", [
            'export interface StackedLeafNestingResource',
            '    leaf_new: Supplier[];',
            '    leaf_make: Supplier[];',
        ]))->and(publishedImportLines(StackedLeafNestingResource::class))
            ->toBe(["import type { Supplier } from '../../../../../../workbench/app/models';"]);
    });
});

// The narrowing backs the proxy by the proven model; an ancestor's body still runs as the subclass Laravel builds.
test('an instanceof arm in an ancestor\'s body keeps the keys of the class Laravel builds', function () {
    expect(publishedCollectionBody(SortedTagsResource::class))
        ->toBe("export interface SortedTagsResource\n    tags: string[] | null;")
        ->and(publishedCollectionBody(KeyedSortedTagsResource::class))
        ->toBe("export interface KeyedSortedTagsResource\n    tags: Record<number, string> | null;");
});

test('a resource key holding a collection sends its own toArray() keys unless that body is the collected list', function () {
    expect(publishedCollectionBody(StubNestingResource::class))->toBe(implode("\n", [
        'export interface StubNestingResource',
        '    stub_new: StubGadgetResource[];',
        '    stub_make: StubGadgetResource[];',
        '    resource_new: StubGadgetResource[];',
        '    body_new: InheritedBodyParentCollection;',
        '    body_make: InheritedBodyParentCollection;',
        '    body_collection: InheritedBodyParentCollection[];',
    ]));
});

// Laravel builds a guessed collection; `#[UseResource]` runs `::collection()`, a list of the class it names.
test('an argument-less toResourceCollection() publishes the collection Laravel guesses', function (string $model, string $type) {
    expect(guessedResourceCollectionType($model))->toBe($type);
})->with([
    'its own body, naming no resource' => [Ratchet::class, 'RatchetCollection'],
    'body-less, naming no resource over an ancestor pick' => [Sprocket::class, 'Supplier[]'],
]);

test('an argument-less toResourceCollection() lists the resource collection #[UseResource] names', function () {
    expect(guessedResourceCollectionType(Cog::class))->toBe('InheritedBodyParentCollection[]');
})->skip(
    ! class_exists('Illuminate\Database\Eloquent\Attributes\UseResource'),
    'UseResource attribute requires Laravel 12.29+',
);

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
    $body->nestedValue(InheritedBodyParentCollection::class);

    DependencyRecorder::start();

    try {
        $body->nestedValue(InheritedBodyParentCollection::class);
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
