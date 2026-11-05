<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\ArrayFilterShapesEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\ArrayMapKeysEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ArrayFilterShapesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ArrayKeysSourcesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ArrayMapSourcesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CollectPipelineRootsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DirectCallsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumCaseHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ZippedModelsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ArrayValuesArms;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Status;
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
    'a shape mixing int and named keys, which a resource re-indexes once only ints are left' => [
        'array_filter([1 => $this->title, "b" => $this->content])',
    ],
    'a shape keyed from 0, which json_encode() writes as a list once only 0..n-1 are left' => [
        'array_filter([0 => $this->title, 2 => $this->content])',
    ],
    'a shape holding a key read at runtime, which may be all it keeps' => ['array_filter([$this->id => $this->title, "a" => $this->title])'],
    'a shape spreading a list, whose keys may be all it keeps' => ['array_filter(["a" => $this->title, ...explode(",", $this->title)])'],
]);

// array_filter() keeps each key it keeps, so each member may be absent, and with no callback no value is null. A
// filter that keeps none sends `[]`, as docs/known-gaps.md records.
it('publishes array_filter() of a shape as the shape with each member optional, dropping a null-only member', function (string $php, string $type) {
    expect(knownFunctionCallType($php))->toBe($type);
})->with([
    'a shape' => ['array_filter(["a" => $this->title])', '{ a?: string }'],
    'a shape holding null' => ['array_filter(["a" => $this->title, "b" => $this->published_at])', '{ a?: string; b?: string }'],
    'a null-only member' => ['array_filter(["a" => $this->title, "b" => null])', '{ a?: string }'],
    'a callback' => [
        'array_filter(["a" => $this->title, "b" => $this->word_count], fn ($v) => $v !== null)',
        '{ a?: string; b?: number | null }',
    ],
    'int keys' => ['array_filter([1 => $this->title, 2 => $this->content])', '{ "1"?: string; "2"?: string }'],
    'only null members, which leave none' => ['array_filter(["b" => null])', 'never[]'],
    'an empty list' => ['array_filter([])', 'never[] | Record<number, never>'],
    'its values' => ['array_values(array_filter(["a" => $this->title, "b" => $this->published_at]))', 'string[]'],
]);

it('publishes array_filter() of an int-keyed shape as the list a resource re-indexes it into, and as an object elsewhere', function () {
    $post = (new Post)->forceFill(['title' => 'T', 'content' => '', 'published_at' => null]);

    expect(publishedResourceKeyTypes(ArrayFilterShapesResource::class, Post::class))->toBe([
        'shape' => '{ a?: string; b?: string }',
        'null_member' => '{ a?: string }',
        'int_keyed' => 'string[]',
        'values' => 'string[]',
    ])->and(json_encode(new ArrayFilterShapesResource($post)->resolve(Request::create('/'))))
        ->toBe('{"shape":{"a":"T"},"null_member":{"a":"T"},"int_keyed":["T"],"values":["T"]}')
        ->and(analyzedKeyTypes(ArrayFilterShapesEvent::class, 'broadcastWith'))->toBe([
            'shape' => '{ a?: string; b?: string }',
            'int_keyed' => '{ "1"?: string; "2"?: string }',
        ])->and(json_encode(new ArrayFilterShapesEvent()->broadcastWith()))->toBe('{"shape":{"a":"T"},"int_keyed":{"1":"T"}}');
});

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

// A shorter array pads with null, and a list may hold null, so each element may be null, yet its model is still the
// one the arm names.
it('imports the model a zip or a callback over a model list names, keeping its null', function () {
    $analysis = resolve(AstEngine::class)->analyze(ZippedModelsResource::class, 'toArray');
    $method = resolve(AstEngine::class)->analyzeMethod(ZippedModelsResource::class, 'toArray');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe([
        'zipped' => '[Comment | null, number | null][]',
        'firsts' => '(Comment | null)[]',
        'maybe' => '(Comment | null)[]',
    ])->and(array_merge(...array_values($analysis->typeImports)))->toContain('Comment')
        ->and($method->channelFqcns('zipped'))->toBe([Comment::class])
        ->and($method->channelFqcns('firsts'))->toBe([Comment::class])
        ->and($method->channelFqcns('maybe'))->toBe([Comment::class]);
});

// Each element is a case, so an untyped parameter holds the enum and its `->value` reads as the enum's value type does.
it('binds an untyped map() or array_map() parameter over enum cases to the enum', function (string $php, string $type, string $enum) {
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    $result = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($expr);

    expect($result['type'])->toBe($type)
        ->and([...(isset($result['directEnumFqcn']) ? [$result['directEnumFqcn']] : []), ...$result['embeddedEnumFqcns'] ?? []])
        ->toContain($enum);
})->with([
    'array_map(), a typed parameter' => ['array_map(fn (\\'.Grade::class.' $g) => $g->value, \\'.Grade::class.'::cases())', 'GradeType[]', Grade::class],
    'array_map(), an untyped parameter' => ['array_map(fn ($g) => $g->value, \\'.Grade::class.'::cases())', 'GradeType[]', Grade::class],
    'array_map(), an int-backed enum' => ['array_map(fn (\\'.Status::class.' $s) => $s->value, \\'.Status::class.'::cases())', 'StatusType[]', Status::class],
    'array_map(), the names' => ['array_map(fn (\\'.Grade::class.' $g) => $g->name, \\'.Grade::class.'::cases())', 'GradeKind[]', Grade::class],
    'collect()->map()->all(), untyped' => ['collect(\\'.Grade::class.'::cases())->map(fn ($g) => $g->value)->all()', 'GradeType[]', Grade::class],
    'collect()->map()->all(), an int-backed enum' => ['collect(\\'.Status::class.'::cases())->map(fn ($s) => $s->value)->all()', 'StatusType[]', Status::class],
    'collect()->map(), kept' => ['collect(\\'.Status::class.'::cases())->map(fn ($s) => $s->value)', 'StatusType[]', Status::class],
    'collect() of case literals' => ['collect([\\'.Grade::class.'::Pass, \\'.Grade::class.'::Fail])->map(fn ($t) => $t->value)->all()', 'GradeType[]', Grade::class],
    'collect()->all() of the cases' => ['collect(\\'.Grade::class.'::cases())->all()', 'GradeType[]', Grade::class],
]);

// A vague helper's body fallback hands its cases over as literals, so the callback reads them again as their enums,
// through a helper's shape and a spread too.
it('maps the cases a vague helper returns with array_map(), reading each as its enum', function () {
    expect(resourceProps(EnumCaseHelperResource::class))->toMatchArray([
        'mapped_values' => 'StatusType[]',
        'mapped_value' => 'StatusType[]',
        'mapped_resources' => 'AsEnum<typeof Status>[]',
        'shape_values' => '{ s: StatusType }',
        'shape_resources' => '{ s: AsEnum<typeof Status> }',
        'shape_icons' => '{ s: string }',
        'spread_values' => '{ s: StatusType; n: StatusType }',
    ]);
});

// PHP stores a key written as a decimal integer, such as `'200'` or `'-3'`, as an int, and keeps any other a string.
it('types array_keys() as the list of its array\'s keys, arm by arm', function (string $php, string $type) {
    expect(knownFunctionCallType($php, ArrayMapSourcesResource::class))->toBe($type);
})->with([
    'a record' => ['array_keys($this->scores)', 'string[]'],
    'a record or null, whose null throws' => ['array_keys($this->options)', 'string[]'],
    'a record or an empty list' => ['array_keys($this->options ?? [])', 'string[]'],
    'a list' => ['array_keys($this->names())', 'number[]'],
    'a list, searched for a value' => ['array_keys($this->names(), "ann")', 'number[]'],
    'a shape' => ['array_keys(["a" => 1, "b" => 2])', "('a' | 'b')[]"],
    'a shape keyed by numbers and a decimal' => ['array_keys(["a" => 1, 200 => 2, "1.5" => 3, "-3" => 4])', "('a' | 200 | '1.5' | -3)[]"],
    'a negative key' => ['array_keys([-3 => 1, "a" => 2])', "(-3 | 'a')[]"],
    'a negative key beside a value nothing constant holds' => ['array_keys([-3 => $this->title, "a" => 2])', "(-3 | 'a')[]"],
    'a key a constant names' => ['array_keys([PHP_INT_SIZE => 1, "a" => 2])', "(8 | 'a')[]"],
    'a key an expression makes' => ['array_keys([1 + 1 => "x", "a" => 2])', "(2 | 'a')[]"],
    'a class-name key' => ['array_keys([\\Workbench\\App\\Enums\\Status::class => 1, "a" => 2])', "('Workbench\\\\App\\\\Enums\\\\Status' | 'a')[]"],
    'a key PHP numbers after a named one' => ['array_keys(["a" => $this->title, $this->id])', "('a' | 0)[]"],
    'an empty list' => ['array_keys([])', 'never[]'],
]);

/**
 * Seeded array literals of 1 to 5 items, each beside the array PHP builds of it: a key a name, a quote, a decimal
 * integer as an int or a string, negative or past JavaScript's safe range, a string PHP keeps though numeric, or none.
 * A key read at runtime or a spread makes the literal's keys inexact; a value read at runtime does not.
 *
 * @return list<array{source: string, array: array<int|string, mixed>, exact: bool}>
 */
function arrayKeysCases(int $seed, int $cases): array
{
    $random = new Randomizer(new Xoshiro256StarStar($seed));
    $pool = ['a', 'b_c', "it's", 'a"b', 'x y', 'é', '0', '7', '-3', '200', '07', '1.5', '-0', ' 1', '1e3', 12, -40, 0, 1, PHP_INT_MAX];
    $literals = [];

    while (count($literals) < $cases) {
        $items = [];
        $array = [];
        $exact = true;

        try {
            foreach (range(1, $random->getInt(1, 5)) as $value) {
                $key = $pool[$random->getInt(0, count($pool) - 1)];

                match ($random->getInt(0, 6)) {
                    0 => [$items[] = (string) $value, $array[] = $value],
                    1 => [$items[] = '$this->title => '.$value, $array['T'] = $value, $exact = false],
                    2 => [$items[] = '...$this->names()', array_push($array, 'ann', 'bob'), $exact = false],
                    3 => [$items[] = '...$this->scores', $array = [...$array, 'x' => 1, 'y' => 2], $exact = false],
                    4 => [$items[] = var_export($key, true).' => $this->title', $array[$key] = 'T'],
                    default => [$items[] = var_export($key, true).' => '.$value, $array[$key] = $value],
                };
            }
        } catch (Error) {
            continue;
        }

        $literals[] = ['source' => '['.implode(', ', $items).']', 'array' => $array, 'exact' => $exact];
    }

    return $literals;
}

// A literal whose keys are all known lists exactly the keys PHP's own array_keys() returns, as JSON carries them: a
// list's keys as `number`, each other key as its literal. Any other is unknown, or admits each key it holds.
it('lists exactly the keys PHP holds of an array literal that names each one, and no wrong key of any other', function () {
    foreach (arrayKeysCases(100, 200) as $case) {
        $published = knownFunctionCallType('array_keys('.$case['source'].')', ArrayMapSourcesResource::class);
        $sent = array_values(array_unique(array_map(
            static fn (int|string $key): string => is_int($key) && abs($key) > 9_007_199_254_740_991 ? 'number' : JsEmitter::toJsLiteral($key),
            array_keys($case['array']),
        )));
        $members = explode(' | ', (string) preg_replace('/^\((.*)\)\[\]$|^(.*)\[\]$/', '$1$2', $published));
        sort($members);
        sort($sent);
        $admits = array_all(array_keys($case['array']), static fn (int|string $key): bool => in_array(is_int($key) ? 'number' : 'string', $members, true)
            || in_array(JsEmitter::toJsLiteral($key), $members, true));

        if ($case['exact']) {
            expect($members)->toBe(array_is_list($case['array']) ? ['number'] : $sent, $case['source'].' published '.$published);
        } else {
            expect($published === 'unknown' || $admits)->toBeTrue($case['source'].' published '.$published);
        }
    }
});

it('leaves array_keys() of a value it cannot read as an array to unknown', function (string $php) {
    expect(knownFunctionCallType($php, ArrayMapSourcesResource::class))->toBe('unknown');
})->with([
    'no array' => ['array_keys()'],
    'a spread' => ['array_keys(...$this->counts())'],
    'a string' => ['array_keys($this->title)'],
    'a value nothing types' => ['array_keys($this->opaque())'],
    'a relation\'s collection, which PHP throws on' => ['array_keys($this->comments)'],
    'a collect() value, which PHP throws on' => ['array_keys(collect($this->names()))'],
    'a key read at runtime' => ['array_keys([$this->title => 1, "a" => 2])'],
    'a key read at runtime, alone' => ['array_keys([$this->id => 1])'],
    'a literal spreading a list' => ['array_keys([...$this->names(), "a" => 1])'],
    'a spread of a record' => ['array_keys(["a" => 1, ...$this->scores])'],
    'a helper\'s literal keyed at runtime' => ['array_keys($this->keyedByTitle())'],
    'a helper\'s literal keyed only at runtime' => ['array_keys($this->keyedById())'],
]);

// PHP throws a TypeError on any object passed as an array, a Collection included.
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

// array_values() and array_filter() keep the type the items publish, and array_map() and array_keys() never had one.
it('warns that array functions over a Collection throw a TypeError', function (string $php, string $type) {
    expect(knownFunctionCallType($php))->toBe($type)
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => PostResource::class,
            'message' => strstr($php, '(', true).'() on line 1 is passed a Collection, which PHP rejects with a TypeError; '
                .'call ->values()->all().',
        ]]);
})->with([
    'array_values() of a relation' => ['array_values($this->comments)', 'Comment[]'],
    'array_values() of collect()' => ['array_values(collect([1, 2]))', 'number[]'],
    'array_filter() of a relation' => ['array_filter($this->comments)', 'Comment[] | Record<number, Comment>'],
    'array_map() of a relation' => ['array_map(fn ($c) => $c->content, $this->comments)', 'unknown'],
    'array_map() of a relation as its second array' => ['array_map(fn ($a, $c) => $a, [1], $this->comments)', 'unknown'],
    'array_keys() of a relation' => ['array_keys($this->comments)', 'unknown'],
]);

it('does not warn of an array function passed an array or an object other than a Collection', function (string $php) {
    knownFunctionCallType($php);

    expect(AnalysisWarnings::all())->toBe([]);
})->with([
    'a relation\'s items' => ['array_values($this->comments->all())'],
    'a model' => ['array_values($this->resource)'],
]);

// PHPDoc's `T[]` and `array<T>`, a native `array` and a cast take any key, so only a list or int-keyed array lists
// numbers; a `??` left read through a null object is null too, so its right operand runs.
it('reads list keys only from a list, and a coalesce left whole only through a non-null chain, as Laravel sends them', function () {
    $post = (new Post)->forceFill(['id' => 1, 'title' => 'T']);
    $post->setRelation('author', null);
    $post->setRelation('comments', new EloquentCollection);

    expect(publishedResourceKeyTypes(ArrayKeysSourcesResource::class, Post::class))->toBe([
        'plain' => 'unknown',
        'doc_array' => 'unknown',
        'native_return' => 'unknown',
        'doc_return' => 'unknown',
        'cast' => 'unknown',
        'spread_default' => 'unknown',
        'bracket_doc' => 'unknown',
        'generic_doc' => 'unknown',
        'collect_plain' => 'unknown',
        'collect_bracket_doc' => 'unknown',
        'list_doc' => 'number[]',
        'int_keyed_doc' => 'number[]',
        'array_key_doc' => '(number | string)[]',
        'author_posts' => 'Post[] | Record<string, number>',
        'first_replies' => 'Comment[] | Record<string, number>',
        'last_replies' => 'Comment[] | Record<string, number>',
        'author_comments' => 'Comment[] | { a: number }',
        'comments_only' => 'Comment[]',
    ])->and(json_encode(new ArrayKeysSourcesResource($post)->resolve(Request::create('/'))))->toBe(
        '{"plain":["x"],"doc_array":["x"],"native_return":["k"],"doc_return":["k"],"cast":["id"],'
        .'"spread_default":[0,"x","y"],"bracket_doc":["a"],"generic_doc":["b"],"collect_plain":["x"],'
        .'"collect_bracket_doc":["a"],"list_doc":[0,1],"int_keyed_doc":[3,5],"array_key_doc":["c"],'
        .'"author_posts":{"x":1,"y":2},"first_replies":{"x":1,"y":2},"last_replies":{"x":1,"y":2},'
        .'"author_comments":{"a":1},"comments_only":[]}',
    );
});
