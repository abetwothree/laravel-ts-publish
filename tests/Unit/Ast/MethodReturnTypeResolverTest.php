<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ConstantKeyFixture;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ConstantKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\RecursiveVagueFixture;
use Workbench\App\Http\Resources\ServiceReturnResource;
use Workbench\App\Models\Post;
use Workbench\App\Services\PostStatsService;

test('a vague array signature falls back to the literal body', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ServiceReturnResource::class), Post::class)->analyze()->properties)->keyBy('name');

    expect($props['quote']['type'])->toBe('{ unit: string; minimum: number; discounted: { unit: string } }')
        ->and($props['tiers']['type'])->toBe('{ "1": string; "2": string }');
});

test('a self-recursive vague method declines instead of looping', function () {
    // The body contributes nothing either way, so both keep the vague reflected type rather than a
    // fabricated shape. The keyed cycle is the one that re-enters, and it terminates.
    expect(resolve(MethodReturnTypeResolver::class)->resolve(RecursiveVagueFixture::class, 'again'))
        ->toBe(['type' => 'unknown[]', 'optional' => false])
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(RecursiveVagueFixture::class, 'againKeyed'))
        ->toBe(['type' => '{ again: unknown[] }', 'optional' => false]);
});

test('a constant key resolves through parent, self and a foreign class', function () {
    // `parent::PARENT_TIER` is 7 and survives as a quoted key, because the subject is not a resource.
    expect(resolve(MethodReturnTypeResolver::class)->resolve(ConstantKeyFixture::class, 'shape'))
        ->toBe(['type' => '{ "7": string; label: string; external: string }', 'optional' => false]);
});

test('a numeric constant key is dropped when the analyzed subject is a resource', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(ConstantKeyResource::class, 'shape'))
        ->toBe(['type' => '{ external: string }', 'optional' => false]);
});

test('a precise declaration is kept, and an absent method declines', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(PostStatsService::class, 'summary'))
        ->toBe(['type' => '{ views: number; likes: number }', 'optional' => false])
        ->and(resolve(MethodReturnTypeResolver::class)->resolve(PostStatsService::class, 'missingMethod'))
        ->toBeNull();
});
