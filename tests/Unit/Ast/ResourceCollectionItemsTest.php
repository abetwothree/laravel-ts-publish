<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\AttributedPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PageItemCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PageKeyedCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PageRawCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubSprocketLeafCollection;

const PAGE_ITEMS = 'PageItemResource[] | LengthAwarePaginator<PageItemResource> | SimplePaginator<PageItemResource> | CursorPaginator<PageItemResource>';
const PAGE_ITEMS_KEYED = 'Record<string, PageItemResource> | LengthAwarePaginator<PageItemResource> | SimplePaginator<PageItemResource> | CursorPaginator<PageItemResource>';
const PAGE_ITEMS_RAW = 'unknown[] | LengthAwarePaginator<unknown> | SimplePaginator<unknown> | CursorPaginator<unknown>';
const PAGE_ITEMS_MODELS = 'Supplier[] | LengthAwarePaginator<Supplier> | SimplePaginator<Supplier> | CursorPaginator<Supplier>';

/** The published type of one expression read inside a collection's toArray(). */
function collectionItemsType(string $subject, string $php): string
{
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;

    return new ResourceAstAnalyzer(new ReflectionClass($subject))->resolve($expr)['type'];
}

describe("a resource collection's \$this->resource holds its items, in a collection or the paginator it was given", function () {
    test('publishes what Laravel sends for each read', function (string $subject, string $php, string $expected) {
        expect(collectionItemsType($subject, $php))->toBe($expected);
    })->with([
        'the value' => [PageItemCollection::class, '$this->resource', PAGE_ITEMS],
        'a helper returning the value, read as a value' => [PageItemCollection::class, '$this->rows()', PAGE_ITEMS],
        'toArray()' => [PageItemCollection::class, '$this->resource->toArray()', PAGE_ITEMS],
        'jsonSerialize()' => [PageItemCollection::class, '$this->resource->jsonSerialize()', PAGE_ITEMS],
        'count()' => [PageItemCollection::class, '$this->resource->count()', 'number'],
        'first()' => [PageItemCollection::class, '$this->resource->first()', 'PageItemResource | null'],
        'last()' => [PageItemCollection::class, '$this->resource->last()', 'PageItemResource | null'],
        'nullsafe first()' => [PageItemCollection::class, '$this->resource?->first()', 'PageItemResource | null'],
        'forwarded first()' => [PageItemCollection::class, '$this->first()', 'PageItemResource | null'],
        'first() with a callback alone' => [PageItemCollection::class, '$this->resource->first(fn ($item) => $item->id > 1)', 'PageItemResource | null'],
        'first() with a default Laravel may send' => [PageItemCollection::class, "\$this->resource->first(null, 'none')", 'unknown'],
        'first() with a named default' => [PageItemCollection::class, "\$this->resource->first(default: 'none')", 'unknown'],
        'last() with a default Laravel may send' => [PageItemCollection::class, "\$this->resource->last(null, 'none')", 'unknown'],
        'isEmpty()' => [PageItemCollection::class, '$this->resource->isEmpty()', 'boolean'],
        'total(), a length-aware paginator only' => [PageItemCollection::class, '$this->resource->total()', 'number'],
        'forwarded total()' => [PageItemCollection::class, '$this->total()', 'number'],
        'static::total(), which PHP sends to __call()' => [PageItemCollection::class, 'static::total()', 'number'],
        'self::total()' => [PageItemCollection::class, 'self::total()', 'number'],
        '$this::total()' => [PageItemCollection::class, '$this::total()', 'number'],
        'static::first()' => [PageItemCollection::class, 'static::first()', 'PageItemResource | null'],
        'first() on the items an instanceof test proves' => [PageItemCollection::class, '($this->resource instanceof \Illuminate\Pagination\LengthAwarePaginator ? $this->resource : null)?->first()', 'PageItemResource | null'],
        'first() on a ternary whose arms both hold the items' => [PageItemCollection::class, '(true ? $this->resource : $this->resource)->first()', 'PageItemResource | null'],
        'perPage(), every paginator' => [PageItemCollection::class, '$this->resource->perPage()', 'number'],
        'currentPage(), not a cursor paginator' => [PageItemCollection::class, '$this->resource->currentPage()', 'number'],
        'lastPage()' => [PageItemCollection::class, '$this->resource->lastPage()', 'number'],
        'hasMorePages()' => [PageItemCollection::class, '$this->resource->hasMorePages()', 'boolean'],
        'nextPageUrl()' => [PageItemCollection::class, '$this->resource->nextPageUrl()', 'string | null'],
        'items()' => [PageItemCollection::class, '$this->resource->items()', 'PageItemResource[]'],
        'getCollection()' => [PageItemCollection::class, '$this->resource->getCollection()', 'PageItemResource[]'],
        'map() stays unread' => [PageItemCollection::class, '$this->resource->map(fn ($item) => $item->id)', 'unknown'],
        'a read on an item stays unread' => [PageItemCollection::class, '$this->resource->first()?->title', 'unknown'],
        'only() keeps the items under the keys it names' => [PageItemCollection::class, "\$this->resource->only(['id'])", 'PageItemResource[] | Record<string, PageItemResource>'],
        'except() keeps the items under the other keys' => [PageItemCollection::class, "\$this->resource->except(['id'])", 'PageItemResource[] | Record<string, PageItemResource>'],
        '$this->collection is unchanged' => [PageItemCollection::class, '$this->collection', 'PageItemResource[]'],
        'preserved keys: the value' => [PageKeyedCollection::class, '$this->resource', PAGE_ITEMS_KEYED],
        'preserved keys: items()' => [PageKeyedCollection::class, '$this->resource->items()', 'Record<string, PageItemResource>'],
        'nothing collected: the value' => [PageRawCollection::class, '$this->resource', PAGE_ITEMS_RAW],
        'nothing collected: toArray()' => [PageRawCollection::class, '$this->resource->toArray()', PAGE_ITEMS_RAW],
        'nothing collected: first()' => [PageRawCollection::class, '$this->resource->first()', 'unknown'],
        'nothing collected: total()' => [PageRawCollection::class, '$this->resource->total()', 'number'],
        'nothing collected: only()' => [PageRawCollection::class, "\$this->resource->only(['id'])", 'unknown[] | Record<string, unknown>'],
        'raw models: the value' => [StubSprocketLeafCollection::class, '$this->resource', PAGE_ITEMS_MODELS],
        'raw models: first()' => [StubSprocketLeafCollection::class, '$this->resource->first()', 'Supplier | null'],
        'raw models: items()' => [StubSprocketLeafCollection::class, '$this->resource->items()', 'Supplier[]'],
        'raw models: only()' => [StubSprocketLeafCollection::class, "\$this->resource->only(['id'])", 'Supplier[] | Record<string, Supplier>'],
        'raw models: $this->collection' => [StubSprocketLeafCollection::class, '$this->collection', 'Supplier[]'],
    ]);

    test('publishes the same items through a scope its caller supplies', function (string $subject, string $expected) {
        $expr = new AstParser()->parseSource('<?php $this->resource;')[0]->expr;
        $scope = new AnalysisScope(new ReflectionClass($subject));

        expect(new ResourceAstAnalyzer(new ReflectionClass($subject), scope: $scope)->resolve($expr)['type'])
            ->toBe($expected);
    })->with([
        'collected items' => [PageItemCollection::class, PAGE_ITEMS],
        'preserved keys' => [PageKeyedCollection::class, PAGE_ITEMS_KEYED],
        'nothing collected' => [PageRawCollection::class, PAGE_ITEMS_RAW],
    ]);

    test('imports the @tolki/types paginators the value names', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => PageItemCollection::class])->content)
            ->toContain("import type { CursorPaginator, LengthAwarePaginator, SimplePaginator } from '@tolki/types';")
            ->toContain('    page: '.PAGE_ITEMS.';')
            ->toContain('    rows: '.PAGE_ITEMS.';')
            ->toContain('    first: PageItemResource | null;');
    });

    test('a collection that names a model and its resource by attribute still publishes the resource list', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => AttributedPostCollection::class])->content)
            ->toContain('    data: PostResource[];');
    })->skip(fn () => ! version_compare(app()->version(), '13', '>='));
});
