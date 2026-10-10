<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalSignatureCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MethodCastFillResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ModelCastFillResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OptionalSignatureCastBroadcastEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OptionalSignatureModelCastResource;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\CastRestoredFillResource;
use Workbench\App\Http\Resources\OptionalSignatureCastResource;

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
