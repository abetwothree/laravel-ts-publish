<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalSignatureCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MethodCastFillBroadcastEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MethodCastFillResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ModelCastFillResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OptionalSignatureCastBroadcastEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OptionalSignatureModelCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SamePatternDeclinedResource;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\CastRestoredFillResource;
use Workbench\App\Http\Resources\OptionalSignatureCastResource;
use Workbench\App\Models\Post;

test('an optional cast on an index signature publishes it required, its value admitting undefined', function () {
    $properties = new ResourceTransformer(OptionalSignatureCastResource::class)->properties;

    expect($properties['[key: `${string}_tag`]'])->toMatchArray(['type' => 'string | undefined', 'optional' => false])
        ->and($properties['[key: `${string}_note`]'])->toMatchArray(['type' => 'number | undefined', 'optional' => false]);
});

test('an optional model or event cast on an index signature is settled the same way', function (array $properties) {
    expect($properties['[key: `${string}_tag`]'])->toMatchArray(['type' => 'string | undefined', 'optional' => false]);
})->with([
    'a model cast' => [fn (): array => new ResourceTransformer(OptionalSignatureModelCastResource::class)->properties],
    'an event cast' => [fn (): array => app(BroadcastEventTransformer::class, [
        'findable' => OptionalSignatureCastBroadcastEvent::class,
    ])->properties],
]);

test('an optional shared-data cast on an index signature prints no ?: after it', function () {
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn(MiddlewareWithOptionalSignatureCast::class);

    expect($analyzer->analyze()['sharedPageProps'] ?? null)
        ->toBe('{ id: number, [key: `${string}_flag`]: boolean | undefined }');
});

test('a publisher-level cast that makes a same-pattern key joinable restores the signature\'s docblock fill', function (string $resource) {
    $properties = new ResourceTransformer($resource)->properties;

    expect($properties['[key: `${string}_tag`]'])->toMatchArray(['type' => 'string | number | undefined', 'optional' => false])
        ->and($properties['main_tag'])->toMatchArray(['type' => 'number', 'optional' => false]);
})->with([
    'a class-level cast' => [CastRestoredFillResource::class],
    'a model-level cast' => [ModelCastFillResource::class],
]);

test('a method cast counts with its cast type in the publisher\'s reconcile, so a resource-typed key it retypes joins the fill', function () {
    $properties = new ResourceTransformer(MethodCastFillResource::class)->properties;

    expect($properties['[key: `${string}_tag`]'])->toMatchArray(['type' => 'string | number | undefined', 'optional' => false])
        ->and($properties['main_tag'])->toMatchArray(['type' => 'number', 'optional' => false]);
});

test('an event\'s broadcastWith() cast counts with its cast type in the reconcile, as a resource method cast does', function () {
    $properties = app(BroadcastEventTransformer::class, ['findable' => MethodCastFillBroadcastEvent::class])->properties;

    expect($properties['[key: `${string}_tag`]'])->toMatchArray(['type' => 'string | number | undefined', 'optional' => false])
        ->and($properties['main_tag'])->toMatchArray(['type' => 'number', 'optional' => false]);
});

test('AstEngine::analyze() reconciles over the method\'s own casts before it fits their keys', function () {
    $properties = collect(resolve(AstEngine::class)->analyze(MethodCastFillResource::class, 'toArray', Post::class)->properties)
        ->keyBy('name');

    expect($properties['[key: `${string}_tag`]']['type'])->toBe('string | number | undefined')
        ->and($properties['main_tag']['type'])->toBe('number');
});

test('a named key the signature it matches cannot take warns once, naming both', function () {
    new ResourceTransformer(SamePatternDeclinedResource::class);
    new ResourceTransformer(SamePatternDeclinedResource::class);

    $messages = array_column(array_filter(
        AnalysisWarnings::all(),
        fn (array $warning): bool => $warning['subject'] === SamePatternDeclinedResource::class,
    ), 'message');

    expect($messages)->toBe([
        'The key "main_tag" cannot share the index signature "[key: `${string}_tag`]" it matches, so the signature keeps its own value; type the key, or rename it out of the pattern.',
        'The key "main_note" cannot share the index signature "[key: `${string}_note`]" it matches, so the signature keeps its own value; type the key, or rename it out of the pattern.',
    ]);
});

test('a key a cast makes joinable warns about nothing', function (string $resource) {
    new ResourceTransformer($resource);

    expect(AnalysisWarnings::all())->toBe([]);
})->with([
    'a class-level cast' => [CastRestoredFillResource::class],
    'a model-level cast' => [ModelCastFillResource::class],
    'a method cast over a resource-typed key' => [MethodCastFillResource::class],
]);
