<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Http\Resources\Json\JsonResource;

use function Orchestra\Testbench\workbench_path;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use Symfony\Component\Finder\Finder;
use Workbench\App\Http\Resources\ClassConstantResource;
use Workbench\App\Http\Resources\StaticCallResource;
use Workbench\App\Http\Resources\UnionHonestyResource;
use Workbench\App\Models\Post;

test('a union records the arm it leaves out, and its published type is unchanged', function () {
    DroppedUnionArms::start();

    try {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(UnionHonestyResource::class), Post::class)->analyze()->properties)->keyBy('name');
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    // One line per recording site: 28/29 reach analyzeClosureUnion(), 31 is TernaryHandler's narrowed instanceof path,
    // 32 is KnownFunctionCallHandler's data_get default, 33 is ConditionalMethodHandler's when() default, 35 is
    // MatchHandler's arm. The last four resolve their arms themselves, so analyzeClosureUnion() alone would miss them.
    expect(array_column($dropped, 'expression'))->toContain('$this->opaqueValue()')
        ->and(array_unique(array_column($dropped, 'subject')))->toBe([UnionHonestyResource::class])
        ->and(array_column($dropped, 'line'))->toBe([28, 29, 31, 32, 33, 35])
        ->and(array_column($dropped, 'site'))->toBe(['closure-union', 'closure-union', 'ternary-narrowed', 'data-get-default', 'conditional-default', 'match-arm'])
        ->and($props['elvis']['type'])->toBe('null')
        ->and($props['ternary']['type'])->toBe('null')
        ->and($props['narrowed']['type'])->toBe('null')
        ->and($props['data_get_default']['type'])->toBe('string | null')
        ->and($props['conditional_default']['type'])->toBe('string')
        ->and($props['conditional_default']['optional'])->toBeFalse()
        ->and($props['match_arm']['type'])->toBe('string')
        ->and($props['still_typed']['type'])->toBe('string | null');
});

test('a coalesce operand the engine cannot type is recorded too', function () {
    DroppedUnionArms::start();

    try {
        resolve(AstEngine::class)->analyzeMethod(StaticCallResource::class);
        resolve(AstEngine::class)->analyzeMethod(ClassConstantResource::class);
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    // Both fixtures say in their own comments that the left operand degrades to unknown, so `??`
    // drops an arm exactly like a ternary does — invisible to the audit until CoalesceHandler records.
    $located = array_map(fn (array $arm): string => class_basename($arm['subject']).':'.$arm['line'].':'.$arm['site'], $dropped);

    expect($located)->toContain('StaticCallResource:67:coalesce-left')
        ->and($located)->toContain('ClassConstantResource:43:coalesce-left');
});

test('the workbench corpus drops no union arm beyond the pinned baseline', function () {
    /** @var list<array{subject: string, line: int, expression: string}> $baseline */
    $baseline = require __DIR__.'/Fixtures/dropped-union-arms-baseline.php';

    DroppedUnionArms::start();

    try {
        foreach (glob(workbench_path('app/Http/Resources/{,*/}*.php'), GLOB_BRACE) ?: [] as $file) {
            $class = LaravelTsPublish::resolveClassFromFile($file);

            if ($class === null || ! is_a($class, JsonResource::class, true) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            resolve(AstEngine::class)->analyzeMethod($class);
        }
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    $new = array_values(array_filter($dropped, fn (array $arm): bool => ! in_array($arm, $baseline, true)));

    expect($new)->toBe([])
        ->and($dropped)->toBe($baseline);
});

// A site no baseline row names has no workbench key whose drop the audit watches fire, so it could go silent unseen.
test('a new recording site cannot go unpinned', function () {
    /** @var list<array{site: string}> $baseline */
    $baseline = require __DIR__.'/Fixtures/dropped-union-arms-baseline.php';
    $parser = new AstParser;
    $finder = new NodeFinder;
    $sites = [];
    $unparsed = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 3).'/src')->name('*.php') as $file) {
        $stmts = $parser->parseSource($file->getContents());

        // parseSource() answers [] for a file that does not parse, which would hide the record() calls in it.
        if ($stmts === []) {
            $unparsed[] = $file->getRelativePathname();

            continue;
        }

        foreach ($finder->findInstanceOf($stmts, StaticCall::class) as $call) {
            if (! $call->class instanceof Name || $call->class->toString() !== DroppedUnionArms::class
                || ! $call->name instanceof Identifier || $call->name->toString() !== 'record') {
                continue;
            }

            // A site that is not a string literal cannot be pinned, so its location stands in and fails below.
            $arg = $call->args[2] ?? null;
            $sites[] = $arg instanceof Arg && $arg->value instanceof String_
                ? $arg->value->value
                : $file->getRelativePathname().':'.$call->getStartLine();
        }
    }

    $remedy = 'A DroppedUnionArms::record() site that no baseline row names. Give it a permanent UnionHonestyResource key '
        .'that drops an arm there, then pin that key\'s drop in dropped-union-arms-baseline.php: a bare row fails the corpus test.';

    expect($unparsed)->toBe([], 'These src/ files do not parse, so the scan read none of their DroppedUnionArms::record() calls.')
        ->and(in_array('closure-union', $sites, true))->toBeTrue('The scan did not find the closure-union recording site, so it may have read no record() call and proves nothing.')
        ->and(array_values(array_diff($sites, array_column($baseline, 'site'))))->toBe([], $remedy);
});
