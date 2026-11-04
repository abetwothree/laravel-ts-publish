<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\ArrayMapKeysEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ArrayMapSourcesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CollectPipelineRootsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DirectCallsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ArrayValuesArms;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Workbench\App\Http\Resources\ArrayMapResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\ReshapedListsPayload;

/**
 * The type one expression resolves to through the resource profile of a resource, PostResource by default, over a Post.
 *
 * @param  class-string  $resource
 */
function knownFunctionCallType(string $php, string $resource = PostResource::class): string
{
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;

    return new ResourceAstAnalyzer(new ReflectionClass($resource), Post::class)->resolve($expr)['type'];
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

// array_filter() keeps each key it keeps, so a list it leaves a gap in is json_encode()'s object of int keys. It throws
// a TypeError on null, so a null arm of its argument never reaches a response.
it('reads array_filter() as its array argument, keys kept, and with no callback drops its null values', function (string $php, string $type) {
    expect(knownFunctionCallType($php))->toBe($type);
})->with([
    'a record or null' => ['array_filter($this->options)', 'Record<string, string>'],
    'a record or null, with a callback' => ['array_filter($this->options, fn ($o) => $o !== "x")', 'Record<string, string>'],
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
    'null alone' => ['array_filter(null)'],
    'a spread' => ['array_filter(...$this->options)'],
    'a string' => ['array_filter($this->title)'],
    'an untyped value' => ['array_filter(json_decode($this->title))'],
    'a shape' => ['array_filter(["a" => $this->title])'],
]);

it('publishes array_filter() as the array Laravel sends, and a gap in a list as an object', function () {
    expect(publishedResourceKeyTypes(DirectCallsResource::class, Post::class))->toMatchArray([
        'filtered' => 'Record<string, string>',
        'filtered_by' => 'Record<string, string>',
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

// array_values() re-indexes, so every arm it reads is a list; PHP throws a TypeError on null, so that arm is left out.
it('reads array_values() of each arm as the list of its values', function () {
    expect(analyzedKeyTypes(ArrayValuesArms::class))->toMatchArray([
        'maybe_tags' => 'string[]',
        'pair' => '[number, string]',
        'none' => 'never[]',
        'post' => 'unknown',
    ]);
});

it('reads array_values() of an empty object as no values', function (string $php) {
    expect(knownFunctionCallType($php))->toBe('never[]');
})->with([
    'an empty object cast to an array' => ['array_values((array) (object) [])'],
]);

it('keeps the element type through array_values()', function () {
    expect(analyzedKeyTypes(ReshapedListsPayload::class))->toMatchArray([
        'tags' => 'string[]',
        'count_values' => 'number[]',
        'size_values' => '(number | string)[]',
        'kept_tags' => 'string[]',
    ])->and(json_encode(new ReshapedListsPayload()->toArray()))
        ->toContain('"tags":["a"],"count_values":[1],"size_values":[1,"x"],"kept_tags":["a"]');
});

// array_map() keeps one array's keys, as collect()->map()->all() does, and lists what two or more arrays map to, each
// shorter array padded with null. A null callback hands back the one array, or zips several into tuples.
it('types array_map() as collect()->map()->all() does, keeping one array\'s keys and listing two or more', function (string $php, string $expected) {
    expect(knownFunctionCallType($php, ArrayMapSourcesResource::class))->toBe($expected);
})->with([
    'typed arrow over a typed list' => ['array_map(fn (array $r): string => $r[\'message\'], $this->rows())', 'string[]'],
    'untyped arrow over an @var list' => ['array_map(fn ($r) => $r[\'message\'], $this->records)', 'string[]'],
    'typed function closure' => ['array_map(function (array $r): string { return $r[\'message\']; }, $this->rows())', 'string[]'],
    'untyped function closure' => ['array_map(function ($r) { return $r[\'message\']; }, $this->records)', 'string[]'],
    'string callable' => ["array_map('strtoupper', \$this->names())", 'string[]'],
    'first-class callable' => ['array_map(strtoupper(...), $this->names())', 'string[]'],
    'first-class method' => ['array_map($this->shout(...), $this->names())', 'string[]'],
    'first-class untyped method' => ['array_map($this->shoutUntyped(...), $this->names())', 'string[]'],
    'method callable' => ['array_map([$this, \'shout\'], $this->names())', 'string[]'],
    'static first-class method' => ['array_map(self::twice(...), $this->counts())', 'number[]'],
    'static method callable' => ['array_map([self::class, \'twice\'], $this->counts())', 'number[]'],
    'static method string' => [
        "array_map('AbeTwoThree\\\\LaravelTsPublish\\\\Tests\\\\Fixtures\\\\Http\\\\Resources\\\\ArrayMapSourcesResource::twice', \$this->counts())",
        'number[]',
    ],
    'null callback, one array' => ['array_map(null, $this->names())', 'string[]'],
    'null callback, two arrays' => ['array_map(null, $this->names(), $this->counts())', '[string | null, number | null][]'],
    'two arrays' => ['array_map(fn ($a, $b) => $a.$b, $this->names(), $this->counts())', 'string[]'],
    'two nullable parameters' => ['array_map(fn (?string $n, ?int $i): string => $n.$i, $this->names(), $this->counts())', 'string[]'],
    'two string-keyed arrays' => ['array_map(fn (int $v, int $w): int => $v + $w, $this->scores, $this->scores)', 'number[]'],
    'string-keyed literal' => ["array_map(fn (\$n) => \$n * 2, ['a' => 1, 'b' => 2])", '{ a: number; b: number }'],
    'array<string, int> property' => ['array_map(fn (int $n) => $n * 2, $this->scores)', 'Record<string, number>'],
    'array<string, int> property, untyped body' => ['array_map(fn ($v) => $v > 1, $this->scores)', 'Record<string, boolean>'],
    'tuple literal' => ["array_map(fn (string \$v): int => strlen(\$v), ['ab', 'c'])", 'number[]'],
    'model attribute rows' => ["array_map(fn (array \$r): string => \$r['message'], [['message' => \$this->title]])", 'string[]'],
    'shape rows' => ["array_map(fn (array \$r): array => ['m' => \$r['message']], \$this->rows())", '{ m: string }[]'],
    'untyped shape rows' => ["array_map(fn (\$r) => ['m' => \$r['message'], 'n' => strlen(\$r['message'])], \$this->rows())", '{ m: string; n: number }[]'],
    'untyped models' => ['array_map(fn ($c) => $c->content, $this->comments->all())', 'string[]'],
    'typed models' => ['array_map(fn (\\Workbench\\App\\Models\\Comment $c) => $c->content, $this->comments->all())', 'string[]'],
    'vague root, typed callback' => [
        "array_map(fn (\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\ValueObjects\\MapOption \$c) => ['id' => \$c->itemId], \$this->opaque())",
        '{ id: number }[] | Record<string, { id: number }>',
    ],
]);

it('publishes array_map() over an @var local and int-keyed arrays as the lists a resource sends', function () {
    expect(publishedResourceKeyTypes(ArrayMapSourcesResource::class, Post::class))->toMatchArray([
        'typed_local' => 'string[]',
        'untyped_local' => 'string[]',
        'gap_keyed' => 'string[]',
        'filtered' => 'string[]',
    ]);
});

it('publishes array_map() outside a resource with the keys json_encode() writes', function () {
    expect(analyzedKeyTypes(ArrayMapKeysEvent::class, 'broadcastWith'))->toBe([
        'typed_arrow' => 'string[]',
        'string_keyed_literal' => '{ a: number; b: number }',
        'string_keyed_prop' => 'Record<string, number>',
        'gap_keyed_literal' => '{ "1": string; "3": string }',
        'two_arrays_string_keyed' => 'number[]',
    ])->and(json_encode(new ArrayMapKeysEvent()->broadcastWith()))->toBe(
        '{"typed_arrow":["a","b"],"string_keyed_literal":{"a":2,"b":4},"string_keyed_prop":{"x":2,"y":4},'
        .'"gap_keyed_literal":{"1":"A","3":"B"},"two_arrays_string_keyed":[2,4]}',
    );
});

it('publishes the workbench array_map() keys as the JSON the resource sends', function () {
    $post = new Post;
    $post->setRelation('comments', new EloquentCollection([new Comment(['content' => 'first']), new Comment(['content' => 'second'])]));

    expect(publishedResourceKeyTypes(ArrayMapResource::class, Post::class))->toBe([
        'messages' => 'string[]',
        'doubled' => '{ low: number; high: number }',
        'zipped' => '[string | null, number | null][]',
        'names' => 'string[]',
    ])->and(json_encode(new ArrayMapResource($post)->resolve(Request::create('/'))))->toBe(
        '{"messages":["first","second"],"doubled":{"low":2,"high":4},"zipped":[["a",1],["b",2],[null,3]],'
        .'"names":["FIRST","SECOND"]}',
    );
});

it('imports the enum a null-callback zip of an enum list names', function () {
    $analysis = resolve(AstEngine::class)->analyze(CollectPipelineRootsResource::class, 'toArray', Post::class);
    $zipped = array_values(array_filter($analysis->properties, fn (array $property): bool => $property['name'] === 'zipped_grades'));

    expect($zipped[0]['type'])->toBe('[GradeType | null, number | null][]')
        ->and(array_merge(...array_values($analysis->typeImports)))->toContain('GradeType');
});

// PHP throws a TypeError on any object passed as an array, a Collection included; Task 7 warns of it.
it('leaves array_map() over a Collection, or a callable it cannot read, to unknown', function (string $php) {
    expect(knownFunctionCallType($php, ArrayMapSourcesResource::class))->toBe('unknown');
})->with([
    'a relation\'s collection' => ['array_map(fn ($c) => $c->content, $this->comments)'],
    'a collect() value' => ["array_map('strtoupper', collect(\$this->names()))"],
    'no array' => ['array_map(fn ($v) => $v)'],
    'a spread' => ['array_map(fn ($v) => $v, ...$this->counts())'],
    'a string' => ['array_map(fn ($v) => $v, $this->title)'],
    'a callable variable nothing reads' => ['array_map($callback, $this->names())'],
    'a function no one declares' => ["array_map('no_such_function_anywhere', \$this->names())"],
    'an untyped body over a vague root' => ['array_map(fn ($v) => $v, $this->opaque())'],
]);
