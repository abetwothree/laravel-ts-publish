<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\FrameworkValueReadsCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\FrameworkValueReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonSerializeLoopResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParentAttributesResolveResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParentFrameworkCallsChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParentFrameworkCallsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParentListWithResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParentResolveBodylessCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParentResolveCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParentWithBranchesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParentWithWrittenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ResolveLoopResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ToArraySpreadsWithResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\TagResource;

/**
 * The members the engine reads off one method, one `name: type` or `name?: type` per key in order, or the flat type
 * that is its whole answer.
 *
 * @param  class-string  $class
 */
function parentCallMembers(string $class, string $method): string
{
    $analysis = resolve(AstEngine::class)->analyzeMethod($class, $method);

    return $analysis->flatTypeAlias ?? implode('; ', array_map(
        fn (array $p): string => $p['name'].($p['optional'] ? '?' : '').': '.$p['type'],
        $analysis->properties,
    ));
}

test('a JsonResource method a parent:: call reaches reads what Laravel runs for the resource', function (string $class, string $method, string $expected) {
    expect(parentCallMembers($class, $method))->toBe($expected);
})->with([
    'parent::with() is the $with default' => [
        ParentFrameworkCallsResource::class, 'with', 'version: string; flags: { beta: boolean }; extra: string',
    ],
    'parent::resolve() is the resource\'s toArray()' => [
        ParentFrameworkCallsResource::class, 'resolve', 'id: number; name: string; resolved: boolean',
    ],
    'parent::jsonSerialize() is the resource\'s own resolve()' => [
        ParentFrameworkCallsResource::class, 'jsonSerialize', 'id: number; name: string; resolved: boolean; serialized: number',
    ],
    'a subclass\'s toArray() runs under its parent\'s parent::jsonSerialize()' => [
        ParentFrameworkCallsChildResource::class, 'jsonSerialize', 'id: number; slug: string; resolved: boolean; serialized: number',
    ],
    'a collection\'s parent::resolve() is its own toArray()' => [
        ParentResolveCollection::class, 'resolve', 'data: TagResource[]; count: number',
    ],
    'a collection keeping ResourceCollection\'s toArray() returns its bare list' => [
        ParentResolveBodylessCollection::class, 'resolve', 'TagResource[]',
    ],
    'a collection declaring no resolve() runs JsonResource\'s over that list' => [
        FrameworkValueReadsCollection::class, 'resolve', 'TagResource[]',
    ],
]);

test('a collection\'s returned $this->resolve() or $this->jsonSerialize() is its bare list, as a returned parent::resolve() is', function (string $method) {
    expect(parentCallMembers(FrameworkValueReadsCollection::class, $method))->toBe('TagResource[]');
})->with(['listed', 'serialized']);

test('a returned parent::resolve() over the bare list carries the collected resource\'s import', function () {
    $analysis = resolve(AstEngine::class)->analyzeMethod(ParentResolveBodylessCollection::class, 'resolve');

    expect($analysis->flatTypeAliasFqcn)->toBe(TagResource::class)
        ->and($analysis->properties)->toBe([]);
});

test('a variable built on a collection\'s parent::jsonSerialize() holds the bare list under numeric keys', function () {
    expect(parentCallMembers(ParentResolveBodylessCollection::class, 'jsonSerialize'))
        ->toBe('[key: number]: TagResource; count: number');
});

test('a value read of a collection\'s parent::resolve() stays unknown, beside the list its parent::toArray() is', function () {
    expect(parentCallMembers(ParentResolveBodylessCollection::class, 'with'))->toBe('items: unknown; list: TagResource[]');
});

test('a value read of the resource\'s own resolve() or jsonSerialize() outside toArray() is its toArray()', function () {
    expect(parentCallMembers(FrameworkValueReadsResource::class, 'with'))
        ->toBe('snapshot: { id: number; name: string }; serialized: { id: number; name: string }');
});

test('a collection\'s own resolve() is its bare list: numeric keys under a spread, and no keyed object as a value', function () {
    expect(parentCallMembers(FrameworkValueReadsCollection::class, 'meta'))->toBe('[key: number]: TagResource; count: number')
        ->and(parentCallMembers(FrameworkValueReadsCollection::class, 'with'))->toBe('snapshot: unknown[]');
});

test('a variable built on parent::with() is a branch like a literal', function () {
    expect(parentCallMembers(ParentWithBranchesResource::class, 'with'))->toBe('lite?: boolean; extra?: string');
});

test('a parent:: call whose answer the analyzer cannot read leaves the method its own keys', function (string $class, string $method, string $expected) {
    expect(parentCallMembers($class, $method))->toBe($expected);
})->with([
    'a $with a method writes' => [ParentWithWrittenResource::class, 'with', 'extra: string'],
    'a $with default with int keys' => [ParentListWithResource::class, 'with', 'extra: string'],
    'an $attributes property, which resolve() sends in place of toArray()' => [
        ParentAttributesResolveResource::class, 'resolve', 'resolved: boolean',
    ],
]);

test('a toArray() that reads its own jsonSerialize() ends, though Laravel never returns from it', function () {
    expect(parentCallMembers(JsonSerializeLoopResource::class, 'toArray'))->toBe('id: number')
        ->and(parentCallMembers(JsonSerializeLoopResource::class, 'jsonSerialize'))->toBe('id: number');
});

test('a resolve() that its toArray() reads back through jsonSerialize() ends, though Laravel never returns from it', function () {
    expect(parentCallMembers(ResolveLoopResource::class, 'resolve'))->toBe('id: number; resolved: boolean')
        ->and(parentCallMembers(ResolveLoopResource::class, 'toArray'))->toBe('id: number; resolved: boolean');
});

test('a toArray() that spreads its $with keys publishes them', function () {
    expect(collect((new ResourceTransformer(ToArraySpreadsWithResource::class))->properties)
        ->map(fn (array $p, string $name): string => $name.($p['optional'] ? '?' : '').': '.$p['type'])
        ->implode('; '))->toBe('id: number; version: string; extra: string');
});
