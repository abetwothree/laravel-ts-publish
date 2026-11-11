<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\UnreadSpreads;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadLiteralBesideArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadMaybeOverHeldResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadMaybeOverHeldReturnsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadMergeJoinResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadNamedArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadOverrideMappedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadOverrideMergeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadOverrideOrderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadOverrideParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadReturnsJoinResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueBesideArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueKeyBesideArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueMapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueMergeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueOverrideResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueTopResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueUntypedHeldResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueUntypedMemberResource;
use Illuminate\Http\Request;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * What a resource sends for a post with an id, a title and a loaded author.
 *
 * @param  class-string  $resource
 */
function spreadOverrideSent(string $resource): string
{
    $post = new Post(['title' => 'hi']);
    $post->id = 9;
    $post->setRelation('author', new User(['name' => 'Ada']));

    return (string) json_encode((new $resource($post))->resolve(request()));
}

// PHP keeps each key's last write: a later key or spread overrides an earlier one, whichever wrote it.
it('publishes each key as the last write PHP makes, at the top level and nested in a value', function () {
    expect(publishedResourceKeyTypes(SpreadOverrideOrderResource::class, Post::class))->toBe([
        'id' => 'number',
        'by' => 'string',
        'helper_then_literal' => '{ id: number; by: string }',
        'literal_then_helper' => '{ by: User; id: number }',
        'two_helpers' => '{ id: number; by: string }',
        'helper_then_key' => '{ id: number; by: string }',
        'key_then_literal' => '{ by: string }',
        'literal_then_key' => '{ by: User }',
    ])->and(spreadOverrideSent(SpreadOverrideOrderResource::class))->toBe('{"id":9,"by":"hi",'
        .'"helper_then_literal":{"id":9,"by":"hi"},"literal_then_helper":{"by":{"name":"Ada"},"id":9},'
        .'"two_helpers":{"id":9,"by":"hi"},"helper_then_key":{"id":9,"by":"hi"},'
        .'"key_then_literal":{"by":"hi"},"literal_then_key":{"by":{"name":"Ada"}}}');
});

it('publishes a mapped row a literal spread overrides as PHP sends it, with no import the overridden key named', function () {
    expect(publishedResourceKeyTypes(SpreadOverrideMappedResource::class, Post::class))->toBe(['rows' => '{ id: number; by: string }[]'])
        ->and(analyzedImportNames(SpreadOverrideMappedResource::class))->toBe([])
        ->and(spreadOverrideSent(SpreadOverrideMappedResource::class))->toBe('{"rows":[{"id":1,"by":"hi"},{"id":2,"by":"hi"}]}');
});

// The literal spread is read, so the `parent::` spread holds no unread one that could overwrite a key it publishes.
it('reads a parent:: helper whose literal spread overrides its own key', function () {
    expect(publishedResourceKeyTypes(SpreadOverrideParentResource::class, Post::class))->toBe(['a' => 'string'])
        ->and(spreadOverrideSent(SpreadOverrideParentResource::class))->toBe('{"a":"hi"}');
});

it('merges a merge() array whose literal spread overrides the key before it', function () {
    expect(publishedResourceKeyTypes(SpreadOverrideMergeResource::class, Post::class))->toBe(['id' => 'number', 'by' => 'string'])
        ->and(spreadOverrideSent(SpreadOverrideMergeResource::class))->toBe('{"id":9,"by":"hi"}');
});

it('inlines a spread of a literal only where PHP keeps every key it writes a string', function (string $php, array $keys) {
    $statement = new AstParser()->parseSource('<?php '.$php.';')[0];
    assert($statement instanceof Expression && $statement->expr instanceof Array_);
    $array = $statement->expr;
    $inlined = InlineArrayHandler::withNamedLiteralSpreadsInlined($array->items);

    expect(array_map(fn (ArrayItem $item): string => match (true) {
        $item->unpack => '...',
        $item->key instanceof String_ => $item->key->value,
        default => '#',
    }, $inlined))->toBe($keys);
})->with([
    'string keys, in place' => ['[...["a" => 1, "b" => 2], "c" => 3]', ['a', 'b', 'c']],
    'a key PHP keeps a string' => ['[...["06" => 1]]', ['06']],
    'an int key, which PHP renumbers' => ['[...[5 => 1], "c" => 3]', ['...', 'c']],
    'a numeric-string key, which PHP stores an int' => ['[...["5" => 1], "c" => 3]', ['...', 'c']],
    'a keyless item' => ['[...["a"], "c" => 3]', ['...', 'c']],
    'a nested spread' => ['[...[...$x, "a" => 1]]', ['...']],
    'a spread of a variable' => ['[...$x, "c" => 3]', ['...', 'c']],
]);

/**
 * What a resource sends for a post with an id, a title and a loaded author, given the request's input.
 *
 * @param  class-string  $resource
 * @param  array<string, mixed>  $input
 */
function spreadValueSent(string $resource, array $input = []): string
{
    $post = new Post(['title' => 'hi']);
    $post->id = 9;
    $post->setRelation('author', new User(['name' => 'Ada']));

    return (string) json_encode((new $resource($post))->resolve(Request::create('/', 'GET', $input)));
}

/**
 * The keys a spread of a value typed $type writes, each as `name: type`, `?` marking one it may leave out.
 *
 * @param  array<string, mixed>  $extra  more of the value's result
 * @return list<string>|null
 */
function spreadValueKeys(string $type, array $extra = [], ?Expr $value = null): ?array
{
    $scope = new AnalysisScope(new ReflectionClass(SpreadValueOverrideResource::class), Post::class);
    $keys = InlineArrayHandler::spreadKeys($value ?? new Variable('extra'), ['type' => $type, 'optional' => false, ...$extra], $scope);

    return $keys === null ? null : array_map(
        static fn (array $key): string => $key[0].($key[1]['optional'] ? '?' : '').': '.$key[1]['type'],
        $keys,
    );
}

// A key a spread of a value always writes replaces the earlier write, one it may leave out joins it; a later one wins.
it('publishes each key a spread of a value writes as PHP leaves it, nested in a value', function () {
    expect(publishedResourceKeyTypes(SpreadValueOverrideResource::class, Post::class))->toBe([
        'helper_then_value' => '{ id: number; by: string }',
        'key_then_value' => '{ by: string }',
        'helper_then_maybe' => '{ id: number; by: User | string }',
        'helper_then_ternary' => '{ id: number; by: User | string }',
        'helper_then_new_key' => '{ id: number; by: User; extra: string }',
        'value_then_helper' => '{ by: User; id: number }',
        'value_then_key' => '{ by: User }',
    ])->and(analyzedImportNames(SpreadValueOverrideResource::class))->toBe(['User'])
        ->and(spreadValueSent(SpreadValueOverrideResource::class, ['titled' => '1']))->toBe('{'
            .'"helper_then_value":{"id":9,"by":"hi"},"key_then_value":{"by":"hi"},'
            .'"helper_then_maybe":{"id":9,"by":"hi"},"helper_then_ternary":{"id":9,"by":"hi"},'
            .'"helper_then_new_key":{"id":9,"by":{"name":"Ada"},"extra":"hi"},'
            .'"value_then_helper":{"by":{"name":"Ada"},"id":9},"value_then_key":{"by":{"name":"Ada"}}}')
        ->and(spreadValueSent(SpreadValueOverrideResource::class))->toContain(
            '"helper_then_maybe":{"id":9,"by":{"name":"Ada"}},"helper_then_ternary":{"id":9,"by":{"name":"Ada"}}',
        );
});

it('publishes each key a spread of a value writes into the resource\'s own keys', function () {
    expect(publishedResourceKeyTypes(SpreadValueTopResource::class, Post::class))->toBe([
        'id' => 'number',
        'by' => 'string',
        'about' => 'User | string',
        'note?' => 'string',
    ])->and(analyzedImportNames(SpreadValueTopResource::class))->toBe(['User'])
        ->and(spreadValueSent(SpreadValueTopResource::class, ['titled' => '1']))->toBe('{"id":9,"by":"hi","about":"hi","note":"hi"}')
        ->and(spreadValueSent(SpreadValueTopResource::class))->toBe('{"id":9,"by":"hi","about":{"name":"Ada"}}');
});

// TypeScript cannot drop a key from a signature, so a map no key before it fits is left out of a nested value.
it('joins each key before a spread of a map with the map\'s value', function () {
    expect(publishedResourceKeyTypes(SpreadValueMapResource::class, Post::class))->toBe([
        'id' => 'number | string',
        'by' => 'User | string',
        'row' => '{ id: number | string; by: User | string }',
        'tones' => "Omit<Record<string, string>, 'tone'> & { tone: string }",
    ])->and(spreadValueSent(SpreadValueMapResource::class, ['labels' => ['by' => 'x', 'tone' => 'warm']]))->toBe('{'
        .'"id":9,"by":"x","tone":"warm","row":{"id":9,"by":"x","tone":"warm"},"tones":{"tone":"warm","by":"x"}}');
});

it('merges a merge() array whose spread of a local overrides the key before it', function () {
    expect(publishedResourceKeyTypes(SpreadValueMergeResource::class, Post::class))->toBe(['id' => 'number', 'by' => 'string'])
        ->and(spreadValueSent(SpreadValueMergeResource::class))->toBe('{"id":9,"by":"hi"}');
});

it('reads the keys a spread writes from a value typed as one closed object', function (string $type, ?array $keys) {
    expect(spreadValueKeys($type))->toBe($keys);
})->with([
    'a closed object' => ['{ a: string; b?: number }', ['a: string', 'b?: number']],
    'one beside [], each key one it may leave out' => ['{ a: string } | never[]', ['a?: string']],
    'one beside null, which spreads no key' => ['{ a: string } | null', ['a: string']],
    'two objects' => ['{ a: string } | { b: number }', null],
    'a key PHP stores as an int' => ['{ 0: string; a: number }', null],
    'a map' => ['Record<string, string>', null],
    'a signature' => ['{ [key: string]: string }', null],
    'a list' => ['string[]', null],
]);

it('reads no keys from a spread its item loop reads another way, or a value that may hold a key it does not name', function (?Expr $value, array $extra) {
    expect(spreadValueKeys('{ a: string }', $extra, $value))->toBeNull();
})->with([
    'the keys a walk holds' => [fn (): Expr => new Variable(AnalysisScope::walkAlias('data')), []],
    'a parent:: call' => [fn (): Expr => new StaticCall(new Name('parent'), 'helper'), []],
    'a $this-> call' => [fn (): Expr => new MethodCall(new Variable('this'), 'helper'), []],
    'a function call' => [fn (): Expr => new FuncCall(new Name('helper')), []],
    'a resource' => [null, ['resourceFqcn' => SpreadValueOverrideResource::class]],
    'a literal holding a key no name names' => [null, ['unnamedKeys' => true]],
]);

// A mark is no fact of the value: the keys a marked object names are written, and the object they go into is open too.
it('reads the keys of a value a mark says is open, as of any other closed object', function () {
    expect(spreadValueKeys('{ a: string }', ['openReadings' => ["\0{ a: string }"]]))->toBe(['a: string']);
});

it('reads the value a spread of a map may write under any key', function (string $type, ?string $value) {
    $scope = new AnalysisScope(new ReflectionClass(SpreadValueOverrideResource::class), Post::class);

    expect(InlineArrayHandler::spreadSignature(new Variable('labels'), ['type' => $type, 'optional' => false], $scope)['type'] ?? null)
        ->toBe($value);
})->with([
    'a string-keyed Record' => ['Record<string, string>', 'string'],
    'one beside [] or null' => ['Record<string, boolean> | never[] | null', 'boolean'],
    'a lone string signature, undefined aside' => ['{ [key: string]: number | undefined }', 'number'],
    'a number-keyed Record' => ['Record<number, string>', null],
    'a signature beside a key' => ['{ [key: string]: string; a: string }', null],
    'a closed object' => ['{ a: string }', null],
]);

it('keeps a literal a later arm follows as a spread, where the arm prints over it', function () {
    $statement = new AstParser()->parseSource('<?php [...["a" => 1], ...$x];')[0];
    assert($statement instanceof Expression && $statement->expr instanceof Array_);
    $inlined = InlineArrayHandler::withNamedLiteralSpreadsInlined($statement->expr->items, static fn (int $index): bool => $index === 0);

    expect(array_map(static fn (ArrayItem $item): string => $item->unpack ? '...' : '#', $inlined))->toBe(['...', '...']);
});

// A printed arm stands before every key, so a value it follows stays an arm, giving up the keys before it it writes.
it('keeps a spread of a value before a resource\'s arm as an arm, over the keys before it', function () {
    expect(publishedResourceKeyTypes(SpreadValueBesideArmResource::class, Post::class))->toBe([
        'value_then_resource' => 'Omit<{ name: number }, keyof UserResource> & UserResource',
        'resource_then_value' => "Omit<UserResource, 'name'> & { name: number }",
        'key_value_resource' => 'Omit<{ by: string }, keyof UserResource> & UserResource',
        'key_maybe_resource' => "Omit<{ by?: string }, 'by' | keyof UserResource> & Omit<UserResource, 'by'> & { by: User | string }",
    ])->and(analyzedImportNames(SpreadValueBesideArmResource::class))->toBe(['User', 'UserResource'])
        ->and(analyzedImportNames(SpreadValueKeyBesideArmResource::class))->toBe(['UserResource'])
        ->and(spreadValueSent(SpreadValueBesideArmResource::class, ['titled' => '1']))->toBe('{'
            .'"value_then_resource":{"name":"Ada","id":null,"email":null,"role":null},'
            .'"resource_then_value":{"id":null,"name":9,"email":null,"role":null},'
            .'"key_value_resource":{"by":"hi","id":null,"name":"Ada","email":null,"role":null},'
            .'"key_maybe_resource":{"by":"hi","id":null,"name":"Ada","email":null,"role":null}}')
        ->and(spreadValueSent(SpreadValueBesideArmResource::class))->toContain('"key_maybe_resource":{"by":{"name":"Ada"},');
});

it('keeps a spread of a literal before a resource\'s arm as an arm, over the keys before it', function () {
    expect(publishedResourceKeyTypes(SpreadLiteralBesideArmResource::class, Post::class))->toBe([
        'literal_then_resource' => 'Omit<{ name: number }, keyof UserResource> & UserResource',
        'key_literal_resource' => 'Omit<{ by: string }, keyof UserResource> & UserResource',
    ])->and(analyzedImportNames(SpreadLiteralBesideArmResource::class))->toBe(['UserResource'])
        ->and(spreadValueSent(SpreadLiteralBesideArmResource::class))->toBe('{'
            .'"literal_then_resource":{"name":"Ada","id":null,"email":null,"role":null},'
            .'"key_literal_resource":{"by":"hi","id":null,"name":"Ada","email":null,"role":null}}');
});

// PHP may still send the untyped value held where the spread leaves the key out.
it('keeps an untyped key a spread may leave out untyped', function () {
    expect(publishedResourceKeyTypes(SpreadValueUntypedHeldResource::class, Post::class))->toBe(['by' => 'unknown', 'row' => '{ by: unknown }'])
        ->and(spreadValueSent(SpreadValueUntypedHeldResource::class, ['titled' => '1']))->toBe('{"by":"hi","row":{"by":"hi"}}')
        ->and(spreadValueSent(SpreadValueUntypedHeldResource::class))->toBe('{"by":{"a":1},"row":{"by":{"a":1}}}');
});

it('publishes a key a spread\'s untyped member writes over as untyped, whatever the key held', function () {
    expect(publishedResourceKeyTypes(SpreadValueUntypedMemberResource::class, Post::class))->toBe(['by' => 'unknown', 'row' => '{ by: unknown }'])
        ->and(analyzedImportNames(SpreadValueUntypedMemberResource::class))->toBe([])
        ->and(spreadValueSent(SpreadValueUntypedMemberResource::class))->toBe('{"by":{"a":1},"row":{"by":{"a":1}}}');
});

// A later resource or model arm writes over the keys before it: one it always writes goes, one it may write joins.
it('gives up each key before a resource\'s or a model\'s arm to the arm that writes it', function () {
    expect(publishedResourceKeyTypes(SpreadNamedArmResource::class, Post::class))->toBe([
        'key_then_resource' => 'UserResource',
        'helper_then_resource' => 'UserResource',
        'untyped_maybe_resource' => 'Omit<{ name?: number }, keyof UserResource> & UserResource',
        'typed_maybe_resource' => 'Omit<{ name?: number }, keyof UserResource> & UserResource',
        'resource_then_key' => "Omit<UserResource, 'id'> & { id: string }",
        'key_then_model' => "Omit<User, 'name' | 'title'> & { name: number | string; title: number }",
        'hidden_then_model' => "Omit<User, 'password'> & { password: number }",
    ])->and(analyzedImportNames(SpreadNamedArmResource::class))->toBe(['User', 'UserResource'])
        ->and(spreadValueSent(SpreadNamedArmResource::class, ['titled' => '1']))->toBe('{'
            .'"key_then_resource":{"id":null,"name":"Ada","email":null,"role":null},'
            .'"helper_then_resource":{"id":null,"email":null,"name":"Ada","role":null},'
            .'"untyped_maybe_resource":{"name":"Ada","id":null,"email":null,"role":null},'
            .'"typed_maybe_resource":{"name":"Ada","id":null,"email":null,"role":null},'
            .'"resource_then_key":{"id":"x","name":"Ada","email":null,"role":null},'
            .'"key_then_model":{"name":"Ada","title":9},"hidden_then_model":{"password":9,"name":"Ada"}}')
        ->and(spreadValueSent(SpreadNamedArmResource::class))->toContain('"untyped_maybe_resource":{"name":"Ada",');
});

/**
 * An analysis holding `id: number` and `by: string`, then the keys a spread of $extra writes into it.
 *
 * @param  array<string, mixed>  $result  the spread's value
 * @return array{bool, ResourceAnalysis}
 */
function spreadWritten(array $result): array
{
    $analysis = new ResourceAnalysis;
    $analysis->addProperty('id', ['type' => 'number', 'optional' => false]);
    $analysis->addProperty('by', ['type' => 'string', 'optional' => false]);
    $scope = new AnalysisScope(new ReflectionClass(SpreadValueOverrideResource::class), Post::class);

    return [InlineArrayHandler::writeSpread($analysis, new Variable('extra'), $result, $scope), $analysis];
}

it('writes a spread\'s keys in place, the object it writes into open where the value itself is', function (array $result, bool $open) {
    $unread = UnreadSpreads::count();
    [$written, $analysis] = spreadWritten(['optional' => false, ...$result]);

    expect($written)->toBeTrue()
        ->and(array_column($analysis->properties, 'type', 'name'))->toBe(['id' => 'number', 'by' => 'boolean'])
        ->and($analysis->holdsUnnamedKeys)->toBe($open)
        ->and(UnreadSpreads::count() - $unread)->toBe($open ? 1 : 0);
})->with([
    'a closed object' => [['type' => '{ by: boolean }'], false],
    'an object whose own mark says it holds more keys' => [['type' => '{ by: boolean }', 'openReadings' => ["\0{ by: boolean }"]], true],
    'a member\'s mark, which is the member\'s' => [['type' => '{ by: boolean }', 'openReadings' => [".by\0boolean"]], false],
    'a union\'s mark beside null, which is no fact of the object' => [
        ['type' => '{ by: boolean } | null', 'openReadings' => ["\0{ by: boolean } | null"]], false,
    ],
]);

it('writes no key of a spread whose value may hold one it does not name, and joins each held key with a map\'s value', function () {
    [$open, $openAnalysis] = spreadWritten(['type' => '{ by: boolean }', 'optional' => false, 'unnamedKeys' => true]);
    [$map, $mapAnalysis] = spreadWritten(['type' => 'Record<string, boolean>', 'optional' => false]);

    expect($open)->toBeFalse()
        ->and(array_column($openAnalysis->properties, 'type', 'name'))->toBe(['id' => 'number', 'by' => 'string'])
        ->and($map)->toBeFalse()
        ->and(array_column($mapAnalysis->properties, 'type', 'name'))->toBe(['id' => 'number | boolean', 'by' => 'string | boolean']);
});

it('writes each key a spread sets as one it may leave out where the caller says so', function () {
    $analysis = new ResourceAnalysis;
    $analysis->addProperty('id', ['type' => 'number', 'optional' => false]);
    $scope = new AnalysisScope(new ReflectionClass(SpreadValueOverrideResource::class), Post::class);

    InlineArrayHandler::writeSpread(
        $analysis,
        new Variable('extra'),
        ['type' => '{ id: string; by: boolean }', 'optional' => false],
        $scope,
        optional: true,
    );

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['id' => 'number | string', 'by' => 'boolean'])
        ->and(array_column($analysis->properties, 'optional', 'name'))->toBe(['id' => false, 'by' => true])
        ->and(array_column($analysis->properties, 'name'))->toBe(['id', 'by']);
});

// A key a spread or a later key writes again is one key on its path, so a path that sends none leaves it optional.
it('keeps a key optional where a return holds it written twice and another return leaves it out', function () {
    expect(publishedResourceKeyTypes(SpreadReturnsJoinResource::class, Post::class))->toBe([
        'by?' => 'string',
        'note?' => 'string',
        'at?' => 'string',
        'id?' => 'number',
    ])->and(spreadValueSent(SpreadReturnsJoinResource::class, ['titled' => '1']))->toBe('{"by":"hi","note":"hi","at":"hi"}')
        ->and(spreadValueSent(SpreadReturnsJoinResource::class))->toBe('{"id":9}');
});

it('keeps a key optional where a merge call that may not run writes it twice', function () {
    expect(publishedResourceKeyTypes(SpreadMergeJoinResource::class, Post::class))->toBe([
        'id' => 'number',
        'by?' => 'string',
        'note?' => 'string',
        'maybe?' => 'User | string',
        'name?' => 'User',
        'row' => '{ id: number; by?: string }',
    ])->and(spreadValueSent(SpreadMergeJoinResource::class, ['titled' => '1']))
        ->toBe('{"id":9,"by":"hi","note":"hi","row":{"id":9,"by":"hi"}}')
        ->and(spreadValueSent(SpreadMergeJoinResource::class))->toBe('{"id":9,"name":{"name":"Ada"},"row":{"id":9}}')
        ->and(spreadValueSent(SpreadMergeJoinResource::class, ['merged' => '1']))->toContain('"maybe":{"name":"Ada"}')
        ->and(spreadValueSent(SpreadMergeJoinResource::class, ['merged' => '1', 'titled' => '1']))->toContain('"maybe":"hi"');
});

// A write that may not land leaves the held value where it does not; a merge call's value beats a later key.
it('joins a held key with a helper, when() or later write that may not land, as PHP sends either', function () {
    expect(publishedResourceKeyTypes(SpreadMaybeOverHeldResource::class, Post::class))->toBe([
        'id' => 'number',
        'by' => 'User | string',
        'key' => 'User | string',
        'when?' => 'User | string',
        'merged' => 'string | User',
    ])->and(spreadValueSent(SpreadMaybeOverHeldResource::class, ['picked' => '1']))
        ->toBe('{"id":9,"by":"hi","key":"hi","when":"hi","merged":"hi"}')
        ->and(spreadValueSent(SpreadMaybeOverHeldResource::class))
        ->toBe('{"id":9,"by":{"name":"Ada"},"key":{"name":"Ada"},"merged":{"name":"Ada"}}');
});

it('keeps each such joined key optional where another return leaves it out', function () {
    expect(publishedResourceKeyTypes(SpreadMaybeOverHeldReturnsResource::class, Post::class))->toBe([
        'n?' => 'number',
        'id?' => 'number',
        'by?' => 'User | string',
        'key?' => 'User | string',
        'when?' => 'User | string',
        'merged?' => 'string | User',
    ])->and(spreadValueSent(SpreadMaybeOverHeldReturnsResource::class, ['titled' => '1', 'picked' => '1']))
        ->toBe('{"id":9,"by":"hi","key":"hi","when":"hi","merged":"hi"}')
        ->and(spreadValueSent(SpreadMaybeOverHeldReturnsResource::class, ['titled' => '1']))
        ->toBe('{"id":9,"by":{"name":"Ada"},"key":{"name":"Ada"},"merged":{"name":"Ada"}}')
        ->and(spreadValueSent(SpreadMaybeOverHeldReturnsResource::class))->toBe('{"n":1}');
});
