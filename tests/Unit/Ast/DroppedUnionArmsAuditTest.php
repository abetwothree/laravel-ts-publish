<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\ClassConstantResource;
use Workbench\App\Http\Resources\StaticCallResource;
use Workbench\App\Http\Resources\UnionHonestyResource;
use Workbench\App\Models\Post;

use function Orchestra\Testbench\workbench_path;

test('a union records the arm it leaves out, and its published type is unchanged', function () {
    DroppedUnionArms::start();

    try {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(UnionHonestyResource::class), Post::class)->analyze()->properties)->keyBy('name');
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    // One line per recording site: 28/29 reach analyzeClosureUnion(), 31 is TernaryHandler's narrowed
    // instanceof path and 32 is KnownFunctionCallHandler's data_get default. Both of the latter call
    // unionResults() directly, so an audit that only instrumented analyzeClosureUnion() would miss them.
    expect(array_column($dropped, 'expression'))->toContain('$this->opaqueValue()')
        ->and(array_unique(array_column($dropped, 'subject')))->toBe([UnionHonestyResource::class])
        ->and(array_column($dropped, 'line'))->toBe([28, 29, 31, 32])
        ->and(array_column($dropped, 'site'))->toBe(['closure-union', 'closure-union', 'ternary-narrowed', 'data-get-default'])
        ->and($props['elvis']['type'])->toBe('null')
        ->and($props['ternary']['type'])->toBe('null')
        ->and($props['narrowed']['type'])->toBe('null')
        ->and($props['data_get_default']['type'])->toBe('string | null')
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
