<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Model\AccessorBodyAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\GuessesModelResources;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollectionBody;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceResponseBody;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DataKeyPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ItemsKeyPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CommentListPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilteringAccessorModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NestedPostSummary;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostBadgeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostDigestResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostRowDetail;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostRowDigestResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostRowFormatter;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostRowResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostSummaryDetail;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use Workbench\App\Http\Resources\MutualSpreadAResource;
use Workbench\App\Http\Resources\MutualSpreadBResource;
use Workbench\App\Http\Resources\UnionHonestyResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Warehouse;

/**
 * Swap in a MethodLocator that counts each lookup as `own:` or `any:` plus `class::method`: the engine looks a body up
 * once per analysis, so the counts are how many times each body was analyzed.
 *
 * @return ArrayObject<string, int>
 */
$countLookups = function (): ArrayObject {
    /** @var ArrayObject<string, int> $counts */
    $counts = new ArrayObject;

    app()->instance(MethodLocator::class, new class(resolve(AstParser::class), $counts) extends MethodLocator
    {
        /**
         * Keep the counts beside the parser the real locator uses.
         *
         * @param  ArrayObject<string, int>  $counts
         */
        public function __construct(AstParser $parser, private readonly ArrayObject $counts)
        {
            parent::__construct($parser);
        }

        /** Count the lookup, then locate as usual. */
        public function locateOwn(string $class, string $method): ?MethodContext
        {
            $this->counts['own:'.$class.'::'.$method] = ($this->counts['own:'.$class.'::'.$method] ?? 0) + 1;

            return parent::locateOwn($class, $method);
        }

        /** Count the lookup, then locate as usual. */
        public function locate(string $class, string $method): ?MethodContext
        {
            $this->counts['any:'.$class.'::'.$method] = ($this->counts['any:'.$class.'::'.$method] ?? 0) + 1;

            return parent::locate($class, $method);
        }
    });

    return $counts;
};

test('a nested method body is analyzed once per method, not once per path that reaches it', function () use ($countLookups) {
    $lookups = $countLookups();

    $result = resolve(MethodReturnTypeResolver::class)->resolve(NestedPostSummary::class, 'level6');

    // `own:` counts each body's analysis and `any:` each body fallback, which looks the body up before analyzing it.
    $counts = array_map(
        fn (int $level): array => [
            $lookups['own:'.NestedPostSummary::class.'::level'.$level] ?? 0,
            $lookups['any:'.NestedPostSummary::class.'::level'.$level] ?? 0,
        ],
        range(0, 6),
    );

    expect($result['type'] ?? null)->toBe(str_repeat('{ inner: ', 6).'{ views: number }'.str_repeat(' }', 6))
        ->and($counts)->toBe(array_fill(0, 7, [1, 1]));
});

test('a resource spread twice inside another analysis is analyzed once', function () use ($countLookups) {
    $lookups = $countLookups();

    $properties = resolve(AstEngine::class)->analyzeMethod(PostCardResource::class)->properties;

    expect(array_column($properties, 'type', 'name'))->toBe(['badge' => 'string', 'header' => 'boolean', 'footer' => 'boolean'])
        ->and($lookups['own:'.PostBadgeResource::class.'::toArray'] ?? 0)->toBe(1);
});

test('an accessor body is analyzed once per model, attribute and import mode', function () use ($countLookups) {
    $lookups = $countLookups();
    $analyzer = resolve(AccessorBodyAnalyzer::class);

    $types = array_map(
        fn (bool $carriesImports): ?string => $analyzer->analyze(FilteringAccessorModel::class, 'comment_list', $carriesImports)['type'] ?? null,
        [true, false, true, false],
    );

    expect($types)->toBe(['Comment[]', 'unknown[]', 'Comment[]', 'unknown[]'])
        ->and($lookups['any:'.FilteringAccessorModel::class.'::commentList'] ?? 0)->toBe(2);
});

test('an import-less read reuses the waterfall with imports instead of running it again', function () {
    $resolver = resolve(ModelAttributeResolver::class);
    $published = $resolver->resolveAttribute(CommentListPost::class, 'comment_list')['type'];
    $invoked = CommentListPost::$invocations;

    // Refining the vague spelling from the tag needs the type with imports, which the read above already worked out.
    $spelled = [
        $resolver->resolveAttribute(CommentListPost::class, 'comment_list', false)['type'],
        $resolver->resolveAttribute(CommentListPost::class, 'comment_list', false)['type'],
    ];
    $afterImportless = CommentListPost::$invocations - $invoked;

    $omitted = $resolver->isOmittedMutator(CommentListPost::class, 'comment_list');
    $models = $resolver->resolveAccessorModelFqcns(CommentListPost::class, 'comment_list');

    expect($published)->toBe('Comment[]')
        ->and($spelled)->toBe(['unknown[]', 'unknown[]'])
        ->and($afterImportless)->toBe(1)
        ->and($omitted)->toBeFalse()
        ->and($models)->toBe([Comment::class])
        ->and(CommentListPost::$invocations - $invoked)->toBe(1);
});

test('a reused body analysis still records every file it read as a cache dependency', function () {
    DependencyRecorder::start();
    new ResourceAstAnalyzer(new ReflectionClass(PostSummaryResource::class))->analyze();
    DependencyRecorder::stop();

    DependencyRecorder::start();

    try {
        $properties = new ResourceAstAnalyzer(new ReflectionClass(PostDigestResource::class))->analyze()->properties;
        $paths = DependencyRecorder::paths();
    } finally {
        DependencyRecorder::stop();
    }

    expect(array_column($properties, 'type', 'name'))->toBe(['summary' => '{ detail: { views: number } }'])
        ->and($paths)->toContain((new ReflectionClass(PostSummaryDetail::class))->getFileName());
});

// A concrete model's declaration binds as a row that exists unless a call passes one no row backs yet; an abstract one
// is narrowed by each argument's class.
test('an untyped helper body is analyzed once per binding its calls pass, a typed one once per class they narrow it to', function () use ($countLookups) {
    $lookups = $countLookups();

    $properties = resolve(AstEngine::class)->analyzeMethod(PostRowResource::class)->properties;

    expect(array_column($properties, 'type', 'name'))->toBe([
        'first' => '{ title: string; detail: { rank: number } }',
        'again' => '{ title: string; detail: { rank: number } }',
        'author' => '{ title: unknown; detail: { rank: number } }',
        'typed' => '{ key: number }',
        'typed_author' => '{ key: number }',
        'titled' => '{ title: string }',
        'titled_new' => '{ title: string }',
    ])
        ->and($lookups['own:'.PostRowFormatter::class.'::row'] ?? 0)->toBe(2)
        ->and($lookups['own:'.PostRowFormatter::class.'::typed'] ?? 0)->toBe(2)
        ->and($lookups['own:'.PostRowFormatter::class.'::titled'] ?? 0)->toBe(2);
});

test('an answer reused for a call that binds the same still records every file it read', function () use ($countLookups) {
    DependencyRecorder::start();
    resolve(AstEngine::class)->analyzeMethod(PostRowResource::class);
    DependencyRecorder::stop();
    $lookups = $countLookups();

    DependencyRecorder::start();

    try {
        $properties = resolve(AstEngine::class)->analyzeMethod(PostRowDigestResource::class)->properties;
        $paths = DependencyRecorder::paths();
    } finally {
        DependencyRecorder::stop();
    }

    // Only the helper's body names PostRowDetail, so only the reused answer's replay records its file.
    expect(array_column($properties, 'type', 'name'))->toBe(['row' => '{ title: string; detail: { rank: number } }'])
        ->and($lookups['own:'.PostRowFormatter::class.'::row'] ?? 0)->toBe(0)
        ->and($paths)->toContain((new ReflectionClass(PostRowDetail::class))->getFileName());
});

test('an analysis reused at the top of a chain counts the union arms it dropped again', function () use ($countLookups) {
    $lookups = $countLookups();
    $engine = resolve(AstEngine::class);

    $before = DroppedUnionArms::dropped();
    $engine->analyzeMethod(UnionHonestyResource::class);
    $computed = DroppedUnionArms::dropped() - $before;
    $engine->analyzeMethod(UnionHonestyResource::class);

    expect($computed)->toBeGreaterThan(0)
        ->and(DroppedUnionArms::dropped() - $before)->toBe(2 * $computed)
        ->and($lookups['own:'.UnionHonestyResource::class.'::toArray'] ?? 0)->toBe(1);
});

// Each spreads the other, so an analysis of one is cut short where it re-enters itself, wherever the chain began.
test('a resource in a spread cycle publishes one shape whichever resource of the cycle is analyzed first', function (bool $otherFirst) {
    if ($otherFirst) {
        new ResourceAstAnalyzer(new ReflectionClass(MutualSpreadAResource::class))->analyze();
    }

    $properties = new ResourceAstAnalyzer(new ReflectionClass(MutualSpreadBResource::class))->analyze()->properties;

    // A key spread twice is published once, at its first position.
    expect(array_values(array_unique(array_column($properties, 'name'))))->toBe(['b_marker', 'a_marker']);
})->with(['alone' => false, 'after the other resource' => true]);

test('whether a collection sends its collected list is read once a run, and again after a reset', function () use ($countLookups) {
    $lookups = $countLookups();
    $body = resolve(ResourceCollectionBody::class);
    $collection = new ReflectionClass(ItemsKeyPostCollection::class);

    $answers = [$body->sendsCollectedList($collection), $body->sendsCollectedList($collection)];
    $readOnce = $lookups['any:'.ItemsKeyPostCollection::class.'::toArray'] ?? 0;
    resolve(AnalysisMemo::class)->reset();
    $body->sendsCollectedList($collection);

    expect($answers)->toBe([false, false])
        ->and($readOnce)->toBe(1)
        ->and($lookups['any:'.ItemsKeyPostCollection::class.'::toArray'] ?? 0)->toBe(2);
});

// The collection reader stands in for the walk, so a second read of the same resource and key reaches it no more.
test('whether a resource body holds a wrap key is read once a run, and records the resource on each read', function () {
    $reads = new ArrayObject;
    $reader = resolve(ResourceCollectionBody::class);

    app()->instance(ResourceCollectionBody::class, new readonly class($reader, $reads)
    {
        /** @param  ArrayObject<int, string>  $reads */
        public function __construct(private ResourceCollectionBody $reader, private ArrayObject $reads) {}

        /** @param  ReflectionClass<object>  $collection */
        public function sendsCollectedList(ReflectionClass $collection): bool
        {
            $this->reads[] = $collection->getName();

            return $this->reader->sendsCollectedList($collection);
        }
    });

    $body = resolve(ResourceResponseBody::class);
    $recorded = function () use ($body): array {
        DependencyRecorder::start();

        try {
            expect($body->holdsWrapKey(DataKeyPostResource::class, 'data'))->toBeTrue();

            return DependencyRecorder::paths();
        } finally {
            DependencyRecorder::stop();
        }
    };

    $first = $recorded();
    $reused = $recorded();

    expect($reads->getArrayCopy())->toBe([DataKeyPostResource::class])
        ->and($first)->toContain((string) new ReflectionClass(DataKeyPostResource::class)->getFileName())
        ->and($reused)->toEqualCanonicalizing($first);
});

test('a reused collected-list read records the files the first one read', function () {
    $body = resolve(ResourceCollectionBody::class);
    $recorded = function () use ($body): array {
        DependencyRecorder::start();

        try {
            $body->sendsCollectedList(new ReflectionClass(ItemsKeyPostCollection::class));

            return DependencyRecorder::paths();
        } finally {
            DependencyRecorder::stop();
        }
    };

    $first = $recorded();

    expect($first)->toContain((string) new ReflectionClass(ItemsKeyPostCollection::class)->getFileName())
        ->and($recorded())->toEqualCanonicalizing($first);
});

// The host's class checks stand in for the guess, so a second guess for the same model reaches them no more.
test('the resource and the collection Laravel guesses for a model are each read once a run, and again after a reset', function () {
    $checks = new ArrayObject;
    $guesser = new class($checks)
    {
        use GuessesModelResources;

        /** @param  ArrayObject<int, string>  $checks */
        public function __construct(private readonly ArrayObject $checks) {}

        /** The resource Laravel's toResource() guesses for the model. */
        public function resource(string $model): ?string
        {
            return $this->resolveResourceForModel($model);
        }

        /**
         * The collection Laravel's toResourceCollection() guesses for the model.
         *
         * @return array<string, string|null>|null
         */
        public function collection(string $model): ?array
        {
            return $this->resolveResourceCollectionForModel($model);
        }

        /** Count the check, then answer as InspectsResourceCalls does. */
        protected function isResourceClass(string $fqcn): bool
        {
            $this->checks[] = $fqcn;

            return class_exists($fqcn) && is_a($fqcn, JsonResource::class, true);
        }

        /** Count the check, then answer as InspectsResourceCalls does. */
        protected function isPublishedResourceClass(string $fqcn): bool
        {
            $this->checks[] = $fqcn;

            return ResourceCollections::isPublishedResource($fqcn);
        }

        /** No guess reads a property fetch. */
        protected function isThisPropertyFetch(Expr $expr): bool
        {
            return false;
        }

        /**
         * No guess reads a relation.
         *
         * @return array<string, mixed>
         */
        protected function resolveModelRelationTypeInfo(string $name, AnalysisScope $scope): array
        {
            return [];
        }
    };

    $reads = [$guesser->resource(Warehouse::class), $guesser->collection(Warehouse::class)];
    $first = count($checks);
    $reused = [$guesser->resource(Warehouse::class), $guesser->collection(Warehouse::class)];
    $afterReuse = count($checks);
    resolve(AnalysisMemo::class)->reset();
    $guesser->resource(Warehouse::class);
    $guesser->collection(Warehouse::class);

    expect($reads)->toBe([WarehouseResource::class, ['collection' => null, 'resource' => WarehouseResource::class]])
        ->and($reused)->toBe($reads)
        ->and($first)->toBeGreaterThan(0)
        ->and($afterReuse)->toBe($first)
        ->and(count($checks))->toBe(2 * $first);
});
