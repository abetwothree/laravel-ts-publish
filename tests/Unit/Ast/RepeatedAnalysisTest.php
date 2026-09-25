<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Model\AccessorBodyAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CommentListPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilteringAccessorModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NestedPostSummary;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostBadgeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostDigestResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostSummaryDetail;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostSummaryResource;
use Workbench\App\Models\Comment;

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
