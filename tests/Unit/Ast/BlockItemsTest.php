<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\OffsetReadHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\OffsetReadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PregMatchResource;
use Illuminate\Http\Request;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use Workbench\App\Models\Post;

/**
 * A post titled Banana, as the fixtures here read it.
 */
function blockItemsPost(): Post
{
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);

    return $post;
}

/**
 * What OffsetReadHandler answers for an offset read of a holder of the given type.
 */
function blockItemsOffsetRead(string $holder, string $read): ?string
{
    $engine = new class($holder) implements ExpressionEngine
    {
        /** The holder's type, answered for whatever is resolved. */
        public function __construct(private readonly string $holder) {}

        /** Types every expression as the holder. */
        public function resolve(Expr $expr): array
        {
            return ['type' => $this->holder, 'optional' => false];
        }

        /** Spreads nothing. */
        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            return null;
        }

        /** Calls no callable it reads. */
        public function invokesCallables(): bool
        {
            return false;
        }

        /** Fails the test: no array is analyzed here. */
        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called here');
        }
    };
    $expr = new AstParser()->parseSource('<?php '.$read.';')[0]->expr;
    assert($expr instanceof ArrayDimFetch);

    return new OffsetReadHandler()->resolve($expr, new AnalysisScope(new ReflectionClass(OffsetReadResource::class)), $engine)['type'] ?? null;
}

test('a literal-key read types the member of the shape its holder binds, and a key the shape lacks declines', function () {
    expect(publishedResourceKeyTypes(OffsetReadResource::class, Post::class))->toBe([
        'param' => '{ a: number | null }',
        'local' => 'string',
        'mapped' => 'number[]',
        'missing' => 'null',
        'tuple' => 'string',
    ])
        ->and(json_encode(new OffsetReadResource(blockItemsPost())->resolve(Request::create('/'))))
        ->toBe('{"param":{"a":1},"local":"x","mapped":[1,2],"missing":null,"tuple":"Banana"}');
});

// A list, a Record and an index signature name no key the array is sure to hold, so their reads decline.
test('an offset read takes the member each arm of its holder names, and declines a holder or key it cannot be sure of', function (string $holder, string $read, ?string $type) {
    expect(blockItemsOffsetRead($holder, $read))->toBe($type);
})->with([
    'an object member' => ['{ a: number; b: string }', '$x["b"]', 'string'],
    'an optional member' => ['{ a?: number }', '$x["a"]', 'number | null'],
    'a nullable member' => ['{ a: string | null }', '$x["a"]', 'string | null'],
    'a quoted key' => ["{ 'a-b': number }", '$x["a-b"]', 'number'],
    'an int key of an object' => ['{ 0: string; 2: boolean }', '$x[2]', 'boolean'],
    'a null arm' => ['{ a: number } | null', '$x["a"]', 'number | null'],
    'two object arms' => ['{ a: number } | { a: string }', '$x["a"]', 'number | string'],
    'a tuple element' => ['[number, string]', '$x[1]', 'string'],
    'a tuple element by a numeric string' => ['[number, string]', '$x["0"]', 'number'],
    'an optional tuple element' => ['[number, string?]', '$x[1]', 'string | null'],
    'a list' => ['string[]', '$x[0]', null],
    'a Record' => ['Record<string, number>', '$x["a"]', null],
    'an index signature' => ['{ [key: string]: number }', '$x["a"]', null],
    'a key one arm lacks' => ['{ a: number } | { b: string }', '$x["a"]', null],
    'a tuple index past its end' => ['[number]', '$x[1]', null],
    'a rest element' => ['[number, ...string[]]', '$x[1]', null],
    'an unknown member' => ['{ a: unknown }', '$x["a"]', null],
    'a member naming a class' => ['{ a: Post }', '$x["a"]', null],
    'a computed key' => ['{ a: number }', '$x[$k]', null],
    'a holder that is only null' => ['null', '$x["a"]', null],
    'a string' => ['string', '$x["a"]', null],
]);

// A named group keys the groups and a flag nests them, so both still publish only the `[]` beside them, a known gap.
test('preg_match() with no flag and no named group writes a list of strings, over a value it may leave in place', function () {
    expect(publishedResourceKeyTypes(PregMatchResource::class, Post::class))->toBe([
        'm' => 'string[] | never[]',
        'bare' => 'string[]',
        'named' => 'never[]',
        'offsets' => 'never[]',
        'kept' => 'unknown',
        'maybe' => 'string[] | never[]',
    ])
        ->and(json_encode(new PregMatchResource(blockItemsPost())->resolve(Request::create('/'))))
        ->toBe('{"m":["Ba","B","a"],"bare":["Ba","B","a"],"named":{"0":"B","first":"B","1":"B"},"offsets":[["B",0],["B",0]],"kept":"none","maybe":[]}')
        ->and(json_encode(new PregMatchResource(blockItemsPost())->resolve(Request::create('/', 'GET', ['match' => 1]))))
        ->toContain('"kept":["B","B"],"maybe":["B","B"]');
});
