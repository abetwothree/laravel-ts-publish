<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithResourceSpellings;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithJsonApiResources;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithResourceSpellings;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\WarehouseResource;

/**
 * The props one middleware shares, read without the filesystem discovery pass.
 *
 * @param  class-string  $middlewareClass
 */
function parityShareType(string $middlewareClass): ?string
{
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn($middlewareClass);

    return $analyzer->analyze()['sharedPageProps'] ?? null;
}

/**
 * The page data one ControllerWithResourceSpellings action renders.
 *
 * @return array<string, mixed>|null
 */
function parityPageData(string $action): ?array
{
    return new InertiaPageAnalyzer()->analyze(['uses' => ControllerWithResourceSpellings::class.'@'.$action]);
}

afterEach(function () {
    JsonResource::flushState();
});

// Inertia resolves shared data as it resolves page props, so one spelling sends one shape in either.
test('each resource spelling publishes the same type as a page prop and as shared data', function () {
    $props = '{ built: { data: WarehouseResource }, made: { data: WarehouseResource }, '
        .'collected: AnonymousResourceCollection<WarehouseResource>, guessed: { data: WarehouseResource }, '
        .'listed: AnonymousResourceCollection<WarehouseResource>, role: AsEnum<typeof Role>, '
        .'statuses: { data: AsEnum<typeof Status>[] } }';

    expect(parityShareType(MiddlewareWithResourceSpellings::class))->toBe($props)
        ->and(parityPageData('index')['pageType'] ?? null)->toBe('Inertia.SharedData & '.$props);
});

// Inertia calls a lazy wrapper's closure and sends its value as a bare prop, kept out of the first load.
test('a resource inside a lazy Inertia wrapper publishes as the bare prop, optional', function () {
    $page = parityPageData('wrapped');
    $props = '{ deferred?: AnonymousResourceCollection<WarehouseResource>, optional?: { data: WarehouseResource }, '
        .'lazy?: AnonymousResourceCollection<WarehouseResource>, statuses?: { data: AsEnum<typeof Status>[] }, '
        .'role?: AsEnum<typeof Role> }';

    expect($page['pageType'] ?? null)->toBe('Inertia.SharedData & '.$props)
        ->and($page['enumConstFqcns'] ?? null)->toBe([Status::class, Role::class])
        ->and($page['externalImports'] ?? null)->toBe(['@tolki/types' => ['AnonymousResourceCollection']]);
});

test('a JSON:API resource publishes the same document as a page prop and as shared data', function () {
    $included = 'UserResource | CommentResource | CategoryResource';
    $props = "{ post: JsonApiDocument<PostResource, {$included}>, author: JsonApiDocument<UserResource, never>, "
        ."posts: JsonApiCollection<PostResource, {$included}> }";

    expect(parityShareType(MiddlewareWithJsonApiResources::class))->toBe($props)
        ->and(parityPageData('jsonApi')['pageType'] ?? null)->toBe('Inertia.SharedData & '.$props);
})->skip(fn (): bool => ! JsonApiResources::shipped(), 'JsonApiResource ships from Laravel 12.45.');

// `typeof Role` reads the const, and no prop spells RoleType, so the page file imports the const and not the type.
test('a page prop an EnumResource wraps imports the const AsEnum reads, not the enum type', function () {
    $page = parityPageData('index');

    expect($page['classFqcns'] ?? null)->toBe([WarehouseResource::class])
        ->and($page['enumConstFqcns'] ?? null)->toBe([Role::class, Status::class])
        ->and($page['externalImports'] ?? null)->toBe(['@tolki/types' => ['AnonymousResourceCollection']]);
});
