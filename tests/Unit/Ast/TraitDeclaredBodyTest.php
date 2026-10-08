<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\DeclaredVarTraitResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTraitShare;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Events\DispatchRelayed;
use Workbench\App\Http\Resources\Concerns\ShapesTraitPayload;
use Workbench\App\Http\Resources\TraitHelperReadResource;
use Workbench\App\Http\Resources\TraitShapedChildResource;
use Workbench\App\Http\Resources\TraitShapedResource;
use Workbench\App\Services\BandQuoteService;

/**
 * The members a resource publishes, one `name: type` or `name?: type` per key, in order.
 *
 * @param  class-string  $class
 */
function traitDeclaredMembers(string $class): string
{
    return collect((new ResourceTransformer($class))->properties)
        ->map(fn (array $p, string $name): string => $name.($p['optional'] ? '?' : '').': '.$p['type'])
        ->implode('; ');
}

test('a toArray() a trait supplies publishes the trait body, not the model dump', function (string $class) {
    expect(traitDeclaredMembers($class))->toBe('id: number; name: string; shaped_by: string');
})->with([
    'the trait user' => [TraitShapedResource::class],
    'a child of the trait user' => [TraitShapedChildResource::class],
]);

test('a broadcastWith() a trait supplies publishes its keys', function () {
    $properties = (new BroadcastEventTransformer(DispatchRelayed::class))->properties;

    expect(array_map(fn (array $p): string => ($p['optional'] ? '?' : '').$p['type'], $properties))
        ->toBe(['dispatchId' => 'number', 'channel' => 'string']);
});

test('a share() a trait supplies publishes its keys', function () {
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn(MiddlewareWithTraitShare::class);

    expect($analyzer->analyze()['sharedPageProps'] ?? null)->toBe('{ appName: string, fromTrait: boolean }');
});

test('the body fallback reads a vague helper a trait supplies, through each receiver', function () {
    expect(traitDeclaredMembers(TraitHelperReadResource::class))->toBe(
        'id: number; own_quote: { band: string; ceiling: number }; service_quote: { band: string; ceiling: number }; '
        .'static_quote: { band: string; ceiling: number }'
    )
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(BandQuoteService::class, 'bandQuote')['type'] ?? null)
        ->toBe('{ band: string; ceiling: number }');
});

test('analyzing a body a trait supplies records the trait file as a dependency', function () {
    DependencyRecorder::start();

    try {
        resolve(AstEngine::class)->analyzeMethod(TraitShapedResource::class);
        $paths = DependencyRecorder::paths();
    } finally {
        DependencyRecorder::stop();
    }

    expect($paths)->toContain((string) (new ReflectionClass(ShapesTraitPayload::class))->getFileName());
});

test('a toArray() a trait supplies reads its @var tags against the trait file', function () {
    expect(traitDeclaredMembers(DeclaredVarTraitResource::class))->toBe('title: string');
});
