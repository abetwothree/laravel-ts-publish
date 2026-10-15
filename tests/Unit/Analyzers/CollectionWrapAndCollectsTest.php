<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaPaginatedBodyController;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Controllers\InertiaNamedCollectionsController;
use Workbench\App\Http\Controllers\InertiaResourceSharedTemplate;
use Workbench\App\Http\Resources\HandoverCollection;
use Workbench\App\Http\Resources\HandoverDigestCollection;
use Workbench\App\Http\Resources\SupplierSummaryCollection;
use Workbench\App\Http\Resources\SupplierSummaryResource;

// Testbench's tearDown flushes JsonResource's shared slot; a class that redeclares $wrap owns a slot nothing resets.
afterEach(function () {
    JsonResource::flushState();
    HandoverCollection::wrap('handovers');
});

/** Analyze a body-less collection as `ts:publish` does. */
function collectionAnalysis(string $class): ResourceAnalysis
{
    return (new ResourceAstAnalyzer(new ReflectionClass($class)))->analyze();
}

/** The page type one `Controller@action` publishes. */
function wrapPageType(string $uses): string
{
    return (string) (new InertiaPageAnalyzer()->analyze(['uses' => $uses])['pageType'] ?? '');
}

describe('a body-less collection reads its live $wrap', function () {
    it('publishes a bare list after JsonResource::withoutWrapping()', function () {
        JsonResource::withoutWrapping();

        $analysis = collectionAnalysis(SupplierSummaryCollection::class);

        expect($analysis->flatTypeAlias)->toBe('SupplierSummaryResource[]')
            ->and($analysis->flatTypeAliasFqcn)->toBe(SupplierSummaryResource::class)
            ->and($analysis->properties)->toBeEmpty();
    });

    it('publishes the key JsonResource::wrap() sets', function () {
        JsonResource::wrap('items');

        $analysis = collectionAnalysis(SupplierSummaryCollection::class);

        expect($analysis->properties)->toHaveCount(1)
            ->and($analysis->properties[0]['name'])->toBe('items')
            ->and($analysis->properties[0]['type'])->toBe('SupplierSummaryResource[]')
            ->and($analysis->nestedResources)->toBe(['items' => SupplierSummaryResource::class]);
    });

    it('keeps a redeclared $wrap, and the subclass sharing it, through JsonResource::withoutWrapping()', function (string $class) {
        JsonResource::withoutWrapping();

        expect(collectionAnalysis($class)->properties[0]['name'])->toBe('handovers');
    })->with([
        'the redeclaring class' => [HandoverCollection::class],
        'its body-less subclass' => [HandoverDigestCollection::class],
    ]);

    it('follows a redeclaring class\'s own wrap() into the subclass sharing its slot', function () {
        HandoverCollection::wrap('crew');

        expect(collectionAnalysis(HandoverDigestCollection::class)->properties[0]['name'])->toBe('crew')
            ->and(collectionAnalysis(SupplierSummaryCollection::class)->properties[0]['name'])->toBe('data');
    });
});

describe('an Inertia collection prop reads the live $wrap', function () {
    it('types an anonymous collection as a bare list after JsonResource::withoutWrapping()', function () {
        JsonResource::withoutWrapping();

        expect(wrapPageType(InertiaResourceSharedTemplate::class.'@resourceAnonymousCollection'))
            ->toBe('Inertia.SharedData & { warehouse_get: WarehouseResource[], warehouse_all: WarehouseResource[] }');
    });

    it('types an anonymous collection under the key JsonResource::wrap() sets, paginated or not', function () {
        JsonResource::wrap('items');

        expect(wrapPageType(InertiaResourceSharedTemplate::class.'@resourceAnonymousCollection'))
            ->toContain('warehouse_get: { items: WarehouseResource[] }')
            ->and(wrapPageType(InertiaResourceSharedTemplate::class.'@resourcePaginatedCollection'))
            ->toContain("warehouses: Omit<JsonResourcePaginator<WarehouseResource>, 'data'> & { items: WarehouseResource[] }");
    });

    it('keeps a paginated anonymous collection\'s data key after JsonResource::withoutWrapping()', function () {
        JsonResource::withoutWrapping();

        expect(wrapPageType(InertiaResourceSharedTemplate::class.'@resourcePaginatedCollection'))
            ->toContain('warehouses: JsonResourcePaginator<WarehouseResource>');
    });

    it('nests a paginated collection\'s own toArray() body under data after JsonResource::withoutWrapping()', function () {
        expect(wrapPageType(InertiaPaginatedBodyController::class.'@index'))
            ->toBe('Inertia.SharedData & { orders: OrderCollection & ResourcePagination }');

        JsonResource::withoutWrapping();

        expect(wrapPageType(InertiaPaginatedBodyController::class.'@index'))
            ->toBe('Inertia.SharedData & { orders: { data: OrderCollection } & ResourcePagination }');
    });

    it('types a paginated named collection as the paginator after JsonResource::withoutWrapping()', function () {
        JsonResource::withoutWrapping();

        expect(wrapPageType(InertiaNamedCollectionsController::class.'@namedCollectionPaginated'))
            ->toBe('Inertia.SharedData & { posts: JsonResourcePaginator<PostResource> }');
    })->skip(fn () => ! version_compare(app()->version(), '13', '>='));
});
