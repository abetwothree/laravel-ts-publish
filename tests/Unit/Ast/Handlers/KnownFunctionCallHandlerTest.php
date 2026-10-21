<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DirectCallsResource;
use Illuminate\Http\Request;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/** The type one expression resolves to through the resource profile of PostResource over a Post. */
function knownFunctionCallType(string $php): string
{
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;

    return new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($expr)['type'];
}

/**
 * The JSON Laravel sends for DirectCallsResource over a post titled Banana with options a=x and an empty b.
 */
function directCallsJson(): string
{
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana', 'options' => ['a' => 'x', 'b' => '']]);

    return (string) json_encode(new DirectCallsResource($post)->resolve(Request::create('/')));
}

// array_filter() keeps each key it keeps, so a list it leaves a gap in is json_encode()'s object of int keys.
it('reads array_filter() as its array argument, keys kept, and with no callback drops its null values', function (string $php, string $type) {
    expect(knownFunctionCallType($php))->toBe($type);
})->with([
    'a record' => ['array_filter($this->options)', 'Record<string, string> | null'],
    'a record, with a callback' => ['array_filter($this->options, fn ($o) => $o !== "x")', 'Record<string, string> | null'],
    'a list' => ['array_filter([1, 2])', 'number[] | Record<number, number>'],
    'a list holding null' => ['array_filter([$this->title, null])', 'string[] | Record<number, string>'],
    'a list holding null, with a callback' => [
        'array_filter([$this->title, null], fn ($t) => $t !== "")',
        '(string | null)[] | Record<number, string | null>',
    ],
    'a list, with a callback and a mode' => ['array_filter([1, 2], fn ($k) => $k > 0, ARRAY_FILTER_USE_KEY)', 'number[] | Record<number, number>'],
    'a null callback, as none' => ['array_filter([$this->title, null], null)', 'string[] | Record<number, string>'],
    'a list of nulls, which leaves none' => ['array_filter([null])', 'never[] | Record<number, never>'],
]);

it('leaves array_filter() of a value it cannot read as an array to unknown', function (string $php) {
    expect(knownFunctionCallType($php))->toBe('unknown');
})->with([
    'no array' => ['array_filter()'],
    'a spread' => ['array_filter(...$this->options)'],
    'a string' => ['array_filter($this->title)'],
    'an untyped value' => ['array_filter(json_decode($this->title))'],
    'a shape' => ['array_filter(["a" => $this->title])'],
]);

it('publishes array_filter() as the array Laravel sends, and a gap in a list as an object', function () {
    expect(publishedResourceKeyTypes(DirectCallsResource::class, Post::class))->toMatchArray([
        'filtered' => 'Record<string, string> | null',
        'filtered_by' => 'Record<string, string> | null',
        'filtered_list' => 'string[] | Record<number, string>',
        'filtered_nulls' => 'string[] | Record<number, string>',
        'filtered_nulls_by' => '(string | null)[] | Record<number, string | null>',
    ])
        ->and(directCallsJson())->toContain('"filtered":{"a":"x"}')
        ->toContain('"filtered_by":{"b":""}')
        ->toContain('"filtered_list":{"1":"anana"}')
        ->toContain('"filtered_nulls":["Banana"]')
        ->toContain('"filtered_nulls_by":["Banana",null]');
});
