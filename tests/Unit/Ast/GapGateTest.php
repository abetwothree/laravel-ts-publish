<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GapModeKeysResource;

// The gap gate's self-test: a green gate run proves nothing unless its switch reached the analyzer.
it('reads a key no @return types in the gap mode while the gap gate runs', function () {
    new ResourceAstAnalyzer(new ReflectionClass(GapModeKeysResource::class))->analyze();

    $untyped = array_values(array_filter(
        resolve(AnalysisMemo::class)->keys(),
        static fn (string $key): bool => preg_match('/^method-return:[^@]+@untypedRead\b/', $key) === 1,
    ));

    expect($untyped)->not->toBeEmpty()
        ->and(array_all($untyped, static fn (string $key): bool => str_ends_with($key, '@gaps')))->toBeTrue();
})->skip(fn (): bool => ! getenv('TS_PUBLISH_GAP_GATE'), 'runs only under the gap gate');
