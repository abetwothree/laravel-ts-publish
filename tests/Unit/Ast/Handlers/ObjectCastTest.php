<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CastObjectResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\ReshapedListsPayload;

/** The type one expression resolves to through the resource profile of PostResource over a Post. */
function objectCastType(string $php): string
{
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;

    return new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($expr)['type'];
}

it('types an (object) cast as the object it encodes, and stdClass as a record', function () {
    expect(analyzedKeyTypes(ReshapedListsPayload::class))->toMatchArray([
        'counts_object' => 'Record<string, number>',
        'tags_object' => 'Record<string, string>',
        'size_object' => '{ width: number; label: string }',
        'empty_object' => 'Record<string, never>',
        'scalar_object' => '{ scalar: string }',
        'counts_array' => 'Record<string, number>',
        'blank' => 'Record<string, unknown>',
        'fresh' => 'Record<string, unknown>',
    ])->and(json_encode(new ReshapedListsPayload()->toArray()))->toContain(
        '"counts_object":{"a":1},"tags_object":{"0":"a"},"size_object":{"width":1,"label":"x"},"empty_object":{},'
        .'"scalar_object":{"scalar":"x"},"counts_array":{"a":1}',
    );
});

it('leaves an object the cast returns unchanged', function () {
    expect(analyzedKeyTypes(ReshapedListsPayload::class)['collection_object'])->toBe('string[]');
});

it('keeps the numeric keys of a cast object in a resource', function () {
    expect(publishedResourceKeyTypes(CastObjectResource::class, Post::class))->toBe([
        'codes' => '{ "10": string; "20": string }',
        'list' => 'string[]',
    ])->and((new CastObjectResource(new Post))->resolve(request()))
        ->toEqual(['codes' => (object) [10 => 'a', 20 => 'b'], 'list' => ['a', 'b']]);
});

// A Carbon date or a Stringable types as `string`, and PHP returns an object it casts unchanged.
it('leaves an (object) cast of a scalar-typed value that is no literal untyped', function (string $php) {
    expect(objectCastType($php))->toBe('unknown');
})->with([
    'a string column' => ['(object) $this->title'],
    'a date or an empty array' => ['(object) ($this->title ? now() : [])'],
    'a Stringable' => ['(object) str($this->title)'],
]);
