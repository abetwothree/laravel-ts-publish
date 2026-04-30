<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use Laravel\Ranger\Collectors\Response as ResponseCollector;
use Laravel\Ranger\Components\JsonResponse;
use Workbench\App\Http\Controllers\InertiaNamedCollectionsController;
use Workbench\App\Http\Controllers\InertiaResourceSharedTemplate;
use Workbench\App\Http\Controllers\InertiaSingleResourceController;
use Workbench\App\Http\Controllers\InertiaTsCastsController;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\WarehouseResource;

// ─── componentToFqn() ─────────────────────────────────────────────

test('converts simple component name to FQN', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->componentToFqn('Dashboard'))
        ->toBe('Inertia.Pages.Dashboard');
});

test('converts slash-separated component to dot-separated FQN', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->componentToFqn('Settings/General'))
        ->toBe('Inertia.Pages.Settings.General');
});

test('converts kebab-case component segments to StudlyCase', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->componentToFqn('settings/two-factor'))
        ->toBe('Inertia.Pages.Settings.TwoFactor');
});

test('converts double-colon separator to dots', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->componentToFqn('Admin::Dashboard'))
        ->toBe('Inertia.Pages.Admin.Dashboard');
});

// ─── analyze() with mocked collector ─────────────────────────────

test('returns null when collector returns empty array', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $mock->shouldReceive('parseResponse')->andReturn([]);

    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->analyze(['uses' => 'Foo@bar']))->toBeNull();
});

test('returns null when collector returns only non-string responses', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $mock->shouldReceive('parseResponse')->andReturn([
        new JsonResponse(['key' => 'value']),
    ]);

    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->analyze(['uses' => 'Foo@bar']))->toBeNull();
});

// ─── rewriteResourceCollections() ────────────────────────────────

test('rewriteResourceCollections passes through TOLKI_TYPES_MAP FQCNs unchanged', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns): array
        {
            return $this->rewriteResourceCollections($typeString, $fqcns);
        }
    };

    $fqcn = 'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection';
    $typeString = 'Inertia.SharedData & { posts: AnonymousResourceCollection<unknown> }';

    [$resultType, $resultFqcns, $externalImports] = $analyzer->expose($typeString, [$fqcn]);

    expect($resultType)->toBe($typeString)
        ->and($resultFqcns)->toBe([$fqcn])
        ->and($externalImports)->toBeEmpty();
});

test('rewriteResourceCollections rewrites named ResourceCollection subclass', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns): array
        {
            return $this->rewriteResourceCollections($typeString, $fqcns);
        }
    };

    $fqcn = PostCollection::class;
    $typeString = 'Inertia.SharedData & { posts: Workbench.App.Http.Resources.PostCollection }';

    [$resultType, $resultFqcns, $externalImports] = $analyzer->expose($typeString, [$fqcn]);

    expect($resultType)->toBe('Inertia.SharedData & { posts: PostCollection }')
        ->and($resultFqcns)->toBe([PostCollection::class])
        ->and($externalImports)->toBeEmpty();
});

// ─── resolveSingularResourceFqcn() ───────────────────────────────

test('resolveSingularResourceFqcn resolves PostCollection to PostResource via #[Collects] attribute', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        public function expose(string $fqcn): ?string
        {
            return $this->resolveSingularResourceFqcn($fqcn);
        }
    };

    expect($analyzer->expose(PostCollection::class))->toBe(PostResource::class);
});

test('resolveSingularResourceFqcn resolves PostCollection to PostResource via naming convention', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        public function expose(string $fqcn): ?string
        {
            return $this->resolveSingularResourceFqcn($fqcn);
        }
    };

    expect($analyzer->expose(PostCollection::class))->toBe(PostResource::class);
});

test('resolveSingularResourceFqcn returns null for non-naming-convention collection', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        public function expose(string $fqcn): ?string
        {
            return $this->resolveSingularResourceFqcn($fqcn);
        }
    };

    // A ResourceCollection without $collects and without XCollection→XResource match
    expect($analyzer->expose('Illuminate\\Http\\Resources\\Json\\ResourceCollection'))->toBeNull();
});

// ─── parseTsCastsFromMethod() ─────────────────────────────────────

test('parseTsCastsFromMethod returns empty arrays for non-existent class', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importMap: array<string, list<string>>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose('NonExistent\\Controller', 'index');

    expect($result['overrides'])->toBeEmpty()
        ->and($result['importMap'])->toBeEmpty();
});

test('parseTsCastsFromMethod returns empty arrays when method has no TsCasts attribute', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importMap: array<string, list<string>>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    // InertiaPageAnalyzer itself has no TsCasts on __construct
    $result = $analyzer->expose(InertiaPageAnalyzer::class, '__construct');

    expect($result['overrides'])->toBeEmpty()
        ->and($result['importMap'])->toBeEmpty();
});

test('parseTsCastsFromMethod extracts plain string overrides from TsCasts attribute', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importMap: array<string, list<string>>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose(InertiaTsCastsController::class, 'index');

    expect($result['overrides'])->toHaveKey('count', 'string')
        ->and($result['overrides'])->toHaveKey('meta', 'PageMeta');
});

test('parseTsCastsFromMethod extracts import map from TsCasts attribute with import key', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{overrides: array<string, string>, importMap: array<string, list<string>>} */
        public function expose(string $class, string $method): array
        {
            return $this->parseTsCastsFromMethod($class, $method);
        }
    };

    $result = $analyzer->expose(InertiaTsCastsController::class, 'index');

    expect($result['importMap'])->toBe(['@workbench/types' => ['PageMeta']]);
});

// ─── analyze() with externalImports ──────────────────────────────

test('analyze returns externalImports from TsCasts attribute on route method', function () {
    // InertiaComponents::getComponent() returns an empty InertiaResponse for unregistered
    // components. TsCasts overrides prevent the early-return path in buildPageType().

    $mock = Mockery::mock(ResponseCollector::class);
    $mock->shouldReceive('parseResponse')->andReturn(['TsCasts/Index']);

    $analyzer = new InertiaPageAnalyzer($mock);

    $result = $analyzer->analyze(['uses' => InertiaTsCastsController::class.'@index']);

    expect($result)->not->toBeNull()
        ->and($result['externalImports'])->toBe(['@workbench/types' => ['PageMeta']]);
});

// ─── rewritePaginatorGenerics() ───────────────────────────────────

test('rewritePaginatorGenerics returns type unchanged when map is empty', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /**
         * @param  list<class-string>  $fqcns
         * @param  array<string, class-string>  $paginatorModelMap
         * @return array{string, list<class-string>}
         */
        public function expose(string $typeString, array $fqcns, array $paginatorModelMap): array
        {
            return $this->rewritePaginatorGenerics($typeString, $fqcns, $paginatorModelMap);
        }
    };

    $fqcn = 'Illuminate\\Pagination\\LengthAwarePaginator';
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Pagination.LengthAwarePaginator<unknown> }';

    [$resultType, $resultFqcns] = $analyzer->expose($typeString, [$fqcn], []);

    expect($resultType)->toBe($typeString)
        ->and($resultFqcns)->toBe([$fqcn]);
});

test('rewritePaginatorGenerics replaces <unknown> with model dot-notation for LengthAwarePaginator', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /**
         * @param  list<class-string>  $fqcns
         * @param  array<string, class-string>  $paginatorModelMap
         * @return array{string, list<class-string>}
         */
        public function expose(string $typeString, array $fqcns, array $paginatorModelMap): array
        {
            return $this->rewritePaginatorGenerics($typeString, $fqcns, $paginatorModelMap);
        }
    };

    $paginatorFqcn = 'Illuminate\\Pagination\\LengthAwarePaginator';
    $modelFqcn = 'Workbench\\App\\Models\\Post';
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Pagination.LengthAwarePaginator<unknown> }';

    [$resultType, $resultFqcns] = $analyzer->expose($typeString, [$paginatorFqcn], ['posts' => $modelFqcn]);

    expect($resultType)->toBe('Inertia.SharedData & { posts: Illuminate.Pagination.LengthAwarePaginator<Workbench.App.Models.Post> }')
        ->and($resultFqcns)->toContain($paginatorFqcn)
        ->and($resultFqcns)->toContain($modelFqcn);
});

test('rewritePaginatorGenerics adds model FQCN to fqcns list', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /**
         * @param  list<class-string>  $fqcns
         * @param  array<string, class-string>  $paginatorModelMap
         * @return array{string, list<class-string>}
         */
        public function expose(string $typeString, array $fqcns, array $paginatorModelMap): array
        {
            return $this->rewritePaginatorGenerics($typeString, $fqcns, $paginatorModelMap);
        }
    };

    $paginatorFqcn = 'Illuminate\\Pagination\\LengthAwarePaginator';
    $modelFqcn = 'Workbench\\App\\Models\\Post';
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Pagination.LengthAwarePaginator<unknown> }';

    [, $resultFqcns] = $analyzer->expose($typeString, [$paginatorFqcn], ['posts' => $modelFqcn]);

    expect($resultFqcns)->toHaveCount(2)
        ->and($resultFqcns)->toContain($modelFqcn);
});

test('rewritePaginatorGenerics does not duplicate model FQCN when already in fqcns', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /**
         * @param  list<class-string>  $fqcns
         * @param  array<string, class-string>  $paginatorModelMap
         * @return array{string, list<class-string>}
         */
        public function expose(string $typeString, array $fqcns, array $paginatorModelMap): array
        {
            return $this->rewritePaginatorGenerics($typeString, $fqcns, $paginatorModelMap);
        }
    };

    $paginatorFqcn = 'Illuminate\\Pagination\\LengthAwarePaginator';
    $modelFqcn = 'Workbench\\App\\Models\\Post';
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Pagination.LengthAwarePaginator<unknown> }';

    [, $resultFqcns] = $analyzer->expose($typeString, [$paginatorFqcn, $modelFqcn], ['posts' => $modelFqcn]);

    expect(array_count_values($resultFqcns)[$modelFqcn])->toBe(1);
});

test('rewritePaginatorGenerics skips prop key not found in type string', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /**
         * @param  list<class-string>  $fqcns
         * @param  array<string, class-string>  $paginatorModelMap
         * @return array{string, list<class-string>}
         */
        public function expose(string $typeString, array $fqcns, array $paginatorModelMap): array
        {
            return $this->rewritePaginatorGenerics($typeString, $fqcns, $paginatorModelMap);
        }
    };

    $paginatorFqcn = 'Illuminate\\Pagination\\LengthAwarePaginator';
    $modelFqcn = 'Workbench\\App\\Models\\Post';
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Pagination.LengthAwarePaginator<unknown> }';

    // 'items' key does not exist in the type string
    [$resultType, $resultFqcns] = $analyzer->expose($typeString, [$paginatorFqcn], ['items' => $modelFqcn]);

    expect($resultType)->toBe($typeString)
        ->and($resultFqcns)->not->toContain($modelFqcn);
});

// ─── rewritePaginatedResourceProps() ──────────────────────────────

test('rewritePaginatedResourceProps returns unchanged when map is empty', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns, array $paginatedResourceProps): array
        {
            return $this->rewritePaginatedResourceProps($typeString, $fqcns, $paginatedResourceProps);
        }
    };

    $typeString = 'Inertia.SharedData & { posts: WarehouseResource }';
    [$resultType, $resultFqcns, $externalImports] = $analyzer->expose($typeString, [WarehouseResource::class], []);

    expect($resultType)->toBe($typeString)
        ->and($resultFqcns)->toBe([WarehouseResource::class])
        ->and($externalImports)->toBeEmpty();
});

test('rewritePaginatedResourceProps appends & ResourcePagination for non-flat resource', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns, array $paginatedResourceProps): array
        {
            return $this->rewritePaginatedResourceProps($typeString, $fqcns, $paginatedResourceProps);
        }
    };

    $typeString = 'Inertia.SharedData & { posts: WarehouseResource }';
    [$resultType, $resultFqcns, $externalImports] = $analyzer->expose($typeString, [WarehouseResource::class], ['posts' => WarehouseResource::class]);

    expect($resultType)->toBe('Inertia.SharedData & { posts: WarehouseResource & ResourcePagination }')
        ->and($resultFqcns)->toBe([WarehouseResource::class])
        ->and($externalImports)->toBe(['@tolki/types' => ['ResourcePagination']]);
});

test('rewritePaginatedResourceProps appends & ResourcePagination for named ResourceCollection (non-flat)', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns, array $paginatedResourceProps): array
        {
            return $this->rewritePaginatedResourceProps($typeString, $fqcns, $paginatedResourceProps);
        }
    };

    $typeString = 'Inertia.SharedData & { posts: PostCollection }';
    [$resultType, $resultFqcns, $externalImports] = $analyzer->expose($typeString, [PostCollection::class], ['posts' => PostCollection::class]);

    expect($resultType)->toBe('Inertia.SharedData & { posts: PostCollection & ResourcePagination }')
        ->and($resultFqcns)->toBe([PostCollection::class])
        ->and($externalImports)->toBe(['@tolki/types' => ['ResourcePagination']]);
});

test('rewritePaginatedResourceProps rewrites flat collection to JsonResourcePaginator<SingularType>', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns, array $paginatedResourceProps): array
        {
            return $this->rewritePaginatedResourceProps($typeString, $fqcns, $paginatedResourceProps);
        }
    };

    $typeString = 'Inertia.SharedData & { posts: PostFlatCollection }';
    [$resultType, $resultFqcns, $externalImports] = $analyzer->expose($typeString, [PostFlatCollection::class], ['posts' => PostFlatCollection::class]);

    expect($resultType)->toBe('Inertia.SharedData & { posts: JsonResourcePaginator<PostResource> }')
        ->and($resultFqcns)->not->toContain(PostFlatCollection::class)
        ->and($resultFqcns)->toContain(PostResource::class)
        ->and($externalImports)->toBe(['@tolki/types' => ['JsonResourcePaginator']]);
});

// ─── analyze() shared-template isolation ──────────────────────────

test('analyze() isolates props when different methods share the same component name - paginated collection method', function () {
    $collector = app(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($collector);

    $result = $analyzer->analyze(['uses' => InertiaResourceSharedTemplate::class.'@resourcePaginatedCollection']);

    // Must only contain the prop declared in this method
    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('warehouses');
});

test('analyze() isolates props when different methods share the same component name - anon collection method', function () {
    $collector = app(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($collector);

    $result = $analyzer->analyze(['uses' => InertiaResourceSharedTemplate::class.'@resourceAnonymousCollection']);

    // Must only contain props declared in this method
    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('warehouse_get')
        ->and($result['pageType'])->toContain('warehouse_all')
        ->and($result['pageType'])->not->toContain('warehouses')
        ->and($result['pageType'])->not->toContain('warehouse_first')
        ->and($result['pageType'])->not->toContain('warehouse_find');
});

test('analyze() isolates props when different methods share the same component name - resource method', function () {
    $collector = app(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($collector);

    $result = $analyzer->analyze(['uses' => InertiaResourceSharedTemplate::class.'@resource']);

    // Must only contain props declared in this method
    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('warehouse_first')
        ->and($result['pageType'])->toContain('warehouse_find')
        ->and($result['pageType'])->not->toContain('warehouses')
        ->and($result['pageType'])->not->toContain('warehouse_get')
        ->and($result['pageType'])->not->toContain('warehouse_all');
});

// ─── rewritePaginatedStaticCollectionProps() ──────────────────────

test('rewritePaginatedStaticCollectionProps returns unchanged when map is empty', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns, array $paginatedStaticCollectionProps): array
        {
            return $this->rewritePaginatedStaticCollectionProps($typeString, $fqcns, $paginatedStaticCollectionProps);
        }
    };

    $anonFqcn = 'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection';
    $typeString = 'Inertia.SharedData & { warehouses: AnonymousResourceCollection<unknown> }';

    [$resultType, $resultFqcns, $externalImports] = $analyzer->expose($typeString, [$anonFqcn, WarehouseResource::class], []);

    expect($resultType)->toBe($typeString)
        ->and($resultFqcns)->toBe([$anonFqcn, WarehouseResource::class])
        ->and($externalImports)->toBeEmpty();
});

test('rewritePaginatedStaticCollectionProps rewrites AnonymousResourceCollection to JsonResourcePaginator', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns, array $paginatedStaticCollectionProps): array
        {
            return $this->rewritePaginatedStaticCollectionProps($typeString, $fqcns, $paginatedStaticCollectionProps);
        }
    };

    $anonFqcn = 'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection';
    $typeString = 'Inertia.SharedData & { warehouses: AnonymousResourceCollection<unknown> }';

    [$resultType, $resultFqcns, $externalImports] = $analyzer->expose($typeString, [$anonFqcn, WarehouseResource::class], ['warehouses' => WarehouseResource::class]);

    expect($resultType)->toBe('Inertia.SharedData & { warehouses: JsonResourcePaginator<WarehouseResource> }')
        ->and($externalImports)->toBe(['@tolki/types' => ['JsonResourcePaginator']]);
});

test('rewritePaginatedStaticCollectionProps adds resource FQCN to fqcns if not already present', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns, array $paginatedStaticCollectionProps): array
        {
            return $this->rewritePaginatedStaticCollectionProps($typeString, $fqcns, $paginatedStaticCollectionProps);
        }
    };

    $anonFqcn = 'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection';
    $typeString = 'Inertia.SharedData & { warehouses: AnonymousResourceCollection<unknown> }';

    [, $resultFqcns] = $analyzer->expose($typeString, [$anonFqcn], ['warehouses' => WarehouseResource::class]);

    expect($resultFqcns)->toContain(WarehouseResource::class);
});

test('rewritePaginatedStaticCollectionProps leaves non-paginated props unchanged', function () {
    $mock = Mockery::mock(ResponseCollector::class);

    $analyzer = new class($mock) extends InertiaPageAnalyzer
    {
        /** @return array{string, list<class-string>, array<string, list<string>>} */
        public function expose(string $typeString, array $fqcns, array $paginatedStaticCollectionProps): array
        {
            return $this->rewritePaginatedStaticCollectionProps($typeString, $fqcns, $paginatedStaticCollectionProps);
        }
    };

    $anonFqcn = 'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection';
    $typeString = 'Inertia.SharedData & { warehouses: AnonymousResourceCollection<unknown>, items: AnonymousResourceCollection<unknown> }';

    // Only 'warehouses' is paginated — 'items' should be left alone
    [$resultType] = $analyzer->expose($typeString, [$anonFqcn, WarehouseResource::class], ['warehouses' => WarehouseResource::class]);

    expect($resultType)->toContain('items: AnonymousResourceCollection<unknown>')
        ->and($resultType)->toContain('warehouses: JsonResourcePaginator<WarehouseResource>');
});

// ─── analyze() paginated Resource::collection() ───────────────────

test('analyze() rewrites Resource::collection($paginator) to JsonResourcePaginator for InertiaNamedCollectionsController@resourceAnonymousPaginated', function () {
    $collector = app(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($collector);

    $result = $analyzer->analyze(['uses' => InertiaNamedCollectionsController::class.'@resourceAnonymousPaginated']);

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('JsonResourcePaginator<PostResource>')
        ->and($result['pageType'])->not->toContain('AnonymousResourceCollection<unknown>');
});

test('analyze() rewrites Resource::collection($paginator) to JsonResourcePaginator for InertiaSingleResourceController@resourcePaginatedCollection', function () {
    $collector = app(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($collector);

    $result = $analyzer->analyze(['uses' => InertiaSingleResourceController::class.'@resourcePaginatedCollection']);

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('JsonResourcePaginator<WarehouseResource>')
        ->and($result['pageType'])->not->toContain('AnonymousResourceCollection<unknown>');
});

test('analyze() rewrites Resource::collection($paginator) to JsonResourcePaginator for InertiaResourceSharedTemplate@resourcePaginatedCollection', function () {
    $collector = app(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($collector);

    $result = $analyzer->analyze(['uses' => InertiaResourceSharedTemplate::class.'@resourcePaginatedCollection']);

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toContain('JsonResourcePaginator<WarehouseResource>')
        ->and($result['pageType'])->not->toContain('AnonymousResourceCollection<unknown>');
});
