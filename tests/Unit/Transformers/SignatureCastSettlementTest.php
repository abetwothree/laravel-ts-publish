<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithOptionalSignatureCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OptionalSignatureCastBroadcastEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OptionalSignatureModelCastResource;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
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
