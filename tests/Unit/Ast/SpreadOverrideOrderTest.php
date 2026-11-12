<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\UnreadSpreads;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\SpreadFoldDeclaredEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\SpreadFoldPropsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadClassMapsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldCertainResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldDeclaredResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldMaybeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldMergeOrderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldMergeWhenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldOpenValueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldRemovableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldResolvedArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldReturnsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadFoldUnionValueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadLiteralBesideArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadMapThenMapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadMaybeEmptyMapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadMaybeOverHeldResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadMaybeOverHeldReturnsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadMergeJoinResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadNamedArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadOverrideMappedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadOverrideMergeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadOverrideOrderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadOverrideParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadRemovableOverHeldResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadReturnsJoinResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueBesideArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueKeyBesideArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueMapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueMergeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueOverrideResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueTopResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueUntypedHeldResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadValueUntypedMemberResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\User as CrmUser;

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

// A later map may write any key, so each key an earlier map wrote holds either map's value.
it('publishes two map spreads in a row as one map of both values', function () {
    expect(publishedResourceKeyTypes(SpreadMapThenMapResource::class, Post::class))->toBe([
        'by' => 'string | number',
        'row' => 'Record<string, string | number>',
        'deep' => '{ in: Record<string, string | number> }',
        'keyed' => "Omit<Record<string, string | number>, 'by'> & { by: string | number }",
        // The map's signature still covers the arm's keys, so this type rejects the nulls the arm sends: a follow-up.
        'armed' => 'Omit<Record<string, string | number>, keyof UserResource> & UserResource',
        'three' => 'Record<string, string | number | boolean>',
    ])->and(analyzedImportNames(SpreadMapThenMapResource::class))->toBe(['UserResource'])
        ->and(spreadValueSent(SpreadMapThenMapResource::class, [
            'labels' => ['k' => 'v', 'by' => 's'],
            'counts' => ['c' => 1, 'by' => 2],
            'flags' => ['f' => true],
        ]))->toBe('{"by":2,"k":"v","c":1,'
            .'"row":{"k":"v","by":2,"c":1},"deep":{"in":{"k":"v","by":2,"c":1}},"keyed":{"by":2,"k":"v","c":1},'
            .'"armed":{"k":"v","by":2,"c":1,"id":null,"name":"Ada","email":null,"role":null},'
            .'"three":{"k":"v","by":2,"c":1,"f":true}}');
});

// A map that may be `[]` writes the keys the map writes, or none; with no map to join it prints no arm, as before.
it('joins a map spread that may be empty into the map beside it', function () {
    expect(publishedResourceKeyTypes(SpreadMaybeEmptyMapResource::class, Post::class))->toBe([
        'coalesced' => 'Record<string, string | number>',
        'or_empty' => 'Record<string, string | number>',
        'empty_first' => 'Record<string, number | string>',
        'before_two' => 'Record<string, boolean | string | number>',
        'keyed' => "Omit<Record<string, string | number>, 'by'> & { by: string | number }",
        'alone' => 'Record<string, unknown>',
        'before_arm' => 'UserResource',
    ])->and(spreadValueSent(SpreadMaybeEmptyMapResource::class, [
        'labels' => ['k' => 'v'],
        'counts' => ['c' => 1],
        'maybe' => ['m' => 2],
        'flags' => ['f' => true],
        'flagged' => '1',
    ]))->toBe('{"coalesced":{"k":"v","m":2},"or_empty":{"k":"v","c":1},"empty_first":{"c":1,"k":"v"},'
        .'"before_two":{"f":true,"k":"v","c":1},"keyed":{"by":"hi","k":"v","c":1},"alone":{"m":2},'
        .'"before_arm":{"m":2,"id":null,"name":"Ada","email":null,"role":null}}')
        ->and(spreadValueSent(SpreadMaybeEmptyMapResource::class, ['labels' => ['k' => 'v'], 'counts' => ['c' => 1]]))
        ->toBe('{"coalesced":{"k":"v"},"or_empty":{"k":"v"},"empty_first":{"k":"v"},'
            .'"before_two":{"k":"v","c":1},"keyed":{"by":"hi","k":"v"},"alone":[],'
            .'"before_arm":{"id":null,"name":"Ada","email":null,"role":null}}');
});

it('publishes two map spreads of classes in a row as one map, importing each class once', function () {
    expect(publishedResourceKeyTypes(SpreadClassMapsResource::class, Post::class))->toBe([
        'row' => 'Record<string, WorkbenchUser | Comment>',
        'twice' => 'Record<string, WorkbenchUser>',
        'alike' => 'Record<string, WorkbenchUser | CrmUser>',
    ])->and(analyzedImportNames(SpreadClassMapsResource::class))->toBe(['Comment', 'User as CrmUser', 'User as WorkbenchUser']);
});

it('merges a merge() array whose spread of a local overrides the key before it', function () {
    expect(publishedResourceKeyTypes(SpreadValueMergeResource::class, Post::class))->toBe(['id' => 'number', 'by' => 'string'])
        ->and(spreadValueSent(SpreadValueMergeResource::class))->toBe('{"id":9,"by":"hi"}');
});

// An open value's named keys are written too (D14), and a union of objects writes per key its arms' union (D15).
it('reads the keys a spread writes from a value typed as objects whose named keys the engine reads', function (string $type, ?array $keys, array $extra = []) {
    expect(spreadValueKeys($type, $extra))->toBe($keys);
})->with([
    'a closed object' => ['{ a: string; b?: number }', ['a: string', 'b?: number']],
    'one beside [], each key one it may leave out' => ['{ a: string } | never[]', ['a?: string']],
    'one beside null, which spreads no key' => ['{ a: string } | null', ['a: string']],
    'two objects, a key one lacks one it may leave out' => ['{ a: string } | { b: number }', ['a?: string', 'b?: number']],
    'two objects writing one key' => ['{ a: string } | { a: number }', ['a: string | number']],
    'a literal holding a key no name names' => ['{ a: string }', ['a: string'], ['unnamedKeys' => true]],
    'a signature beside a key, its named key' => ['{ [key: string]: string; a: string }', ['a: string']],
    'a key PHP stores as an int' => ['{ 0: string; a: number }', null],
    'a map' => ['Record<string, string>', null],
    'a signature' => ['{ [key: string]: string }', null],
    'a list' => ['string[]', null],
]);

it('reads no keys from a spread its item loop reads another way', function (?Expr $value, array $extra) {
    expect(spreadValueKeys('{ a: string }', $extra, $value))->toBeNull();
})->with([
    'the keys a walk holds' => [fn (): Expr => new Variable(AnalysisScope::walkAlias('data')), []],
    'a parent:: call' => [fn (): Expr => new StaticCall(new Name('parent'), 'helper'), []],
    'a $this-> call' => [fn (): Expr => new MethodCall(new Variable('this'), 'helper'), []],
    'a call of the class\'s own method' => [fn (): Expr => new FuncCall(new Name('authored')), []],
    'a resource' => [null, ['resourceFqcn' => SpreadValueOverrideResource::class]],
]);

it('reads the keys a PHP function\'s array writes, as any value\'s', function () {
    expect(spreadValueKeys('{ a: string }', [], new FuncCall(new Name('array_merge'))))->toBe(['a: string']);
});

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
 * A literal holding `id: number` and `by: string`, then the keys a spread of $result writes into it, folded.
 *
 * @param  array<string, mixed>  $result  the spread's value
 * @return array{bool, ResourceAnalysis}
 */
function spreadWritten(array $result): array
{
    $writes = new LiteralWrites;
    $writes->recordKey('id', ['type' => 'number', 'optional' => false], 0);
    $writes->recordKey('by', ['type' => 'string', 'optional' => false], 1);
    $scope = new AnalysisScope(new ReflectionClass(SpreadValueOverrideResource::class), Post::class);
    $written = InlineArrayHandler::recordSpread($writes, new Variable('extra'), $result, $scope, 2);
    $analysis = new ResourceAnalysis;
    $writes->applyTo($analysis, true);

    return [$written, $analysis];
}

// Only a value that reads open counts as an unread spread: its own mark, or the marker, opens the host and counts none.
it('writes a spread\'s keys in place, the object it writes into open where the value itself is', function (array $result, bool $open, bool $counts) {
    $unread = UnreadSpreads::count();
    [$written, $analysis] = spreadWritten(['optional' => false, ...$result]);

    expect($written)->toBeTrue()
        ->and(array_column($analysis->properties, 'type', 'name'))->toBe(['id' => 'number', 'by' => 'boolean'])
        ->and($analysis->holdsUnnamedKeys)->toBe($open)
        ->and(UnreadSpreads::count() - $unread)->toBe($counts ? 1 : 0);
})->with([
    'a closed object' => [['type' => '{ by: boolean }'], false, false],
    'an object whose own mark says it holds more keys' => [['type' => '{ by: boolean }', 'openReadings' => ["\0{ by: boolean }"]], true, false],
    'a member\'s mark, which is the member\'s' => [['type' => '{ by: boolean }', 'openReadings' => [".by\0boolean"]], false, false],
    'a union\'s mark beside null, which is no fact of the object' => [
        ['type' => '{ by: boolean } | null', 'openReadings' => ["\0{ by: boolean } | null"]], false, false,
    ],
    'a value that holds keys no name names (D14)' => [['type' => '{ by: boolean }', 'unnamedKeys' => true], true, true],
]);

it('joins each held key with the value a spread of a map may write over it', function () {
    [$map, $mapAnalysis] = spreadWritten(['type' => 'Record<string, boolean>', 'optional' => false]);

    expect($map)->toBeFalse()
        ->and(array_column($mapAnalysis->properties, 'type', 'name'))->toBe(['id' => 'number | boolean', 'by' => 'string | boolean']);
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

// A write that may not land leaves the held value where it does not, and a when() value replaces it where it does;
// a merge call's value beats a later key.
it('joins a held key with a helper or later write that may not land, and gives it up to a when() value', function () {
    expect(publishedResourceKeyTypes(SpreadMaybeOverHeldResource::class, Post::class))->toBe([
        'id' => 'number',
        'by' => 'User | string',
        'key' => 'User | string',
        'when?' => 'string',
        'merged' => 'string | User',
    ])->and(spreadValueSent(SpreadMaybeOverHeldResource::class, ['picked' => '1']))
        ->toBe('{"id":9,"by":"hi","key":"hi","when":"hi","merged":"hi"}')
        ->and(spreadValueSent(SpreadMaybeOverHeldResource::class))
        ->toBe('{"id":9,"by":{"name":"Ada"},"key":{"name":"Ada"},"merged":{"name":"Ada"}}');
});

// A helper's when() value, passed in or spread from a local, is a MissingValue Laravel removes with the key.
it('keeps a held key optional where a helper writes it as a when() value it was passed or a local holds', function () {
    expect(publishedResourceKeyTypes(SpreadRemovableOverHeldResource::class, Post::class))->toBe([
        'id' => 'number',
        'seeded?' => 'string',
        'local?' => 'string',
        'n' => 'number',
    ])->and(spreadValueSent(SpreadRemovableOverHeldResource::class, ['picked' => '1']))
        ->toBe('{"id":9,"seeded":"hi","local":"hi","n":1}')
        ->and(spreadValueSent(SpreadRemovableOverHeldResource::class))->toBe('{"id":9,"n":1}');
});

it('keeps each such joined key optional where another return leaves it out', function () {
    expect(publishedResourceKeyTypes(SpreadMaybeOverHeldReturnsResource::class, Post::class))->toBe([
        'n?' => 'number',
        'id?' => 'number',
        'by?' => 'User | string',
        'key?' => 'User | string',
        'when?' => 'string',
        'merged?' => 'string | User',
    ])->and(spreadValueSent(SpreadMaybeOverHeldReturnsResource::class, ['titled' => '1', 'picked' => '1']))
        ->toBe('{"id":9,"by":"hi","key":"hi","when":"hi","merged":"hi"}')
        ->and(spreadValueSent(SpreadMaybeOverHeldReturnsResource::class, ['titled' => '1']))
        ->toBe('{"id":9,"by":{"name":"Ada"},"key":{"name":"Ada"},"merged":{"name":"Ada"}}')
        ->and(spreadValueSent(SpreadMaybeOverHeldReturnsResource::class))->toBe('{"n":1}');
});

/**
 * A box holding an app author, its CRM namesake, a title and two statuses.
 */
function foldBox(): FoldBox
{
    return new FoldBox(
        (new User)->forceFill(['id' => 1, 'name' => 'Ada']),
        (new CrmUser)->forceFill(['id' => 2, 'name' => 'Cy']),
        'hi',
        Status::Published,
        CrmStatus::Active,
    );
}

/**
 * What a resource over foldBox() sends, given the request's input.
 *
 * @param  class-string  $resource
 * @param  array<string, mixed>  $input
 */
function foldSent(string $resource, array $input = []): string
{
    return (string) json_encode((new $resource(foldBox()))->resolve(Request::create('/', 'GET', $input)));
}

/**
 * What an EnumResource of the box's status sends.
 */
function foldWrapJson(): string
{
    return (string) json_encode(EnumResource::make(Status::Published));
}

/**
 * Every type and value name a resource's published file imports, sorted.
 *
 * @param  class-string<JsonResource>  $resource
 * @return list<string>
 */
function foldImportNames(string $resource): array
{
    $data = new ResourceTransformer($resource)->data();
    $names = array_merge([], ...array_values($data->typeImports), ...array_values($data->valueImports ?? []));
    sort($names);

    return $names;
}

// D1: a write that sets the key on every path is what PHP sends; the earlier value's class is not imported.
it('publishes a key written again on every path as the later value alone, importing only its class', function () {
    config()->set('ts-publish.enums.use_tolki_package', true);

    expect(foldSent(SpreadFoldCertainResource::class))
        ->toBe('{"crm_then_app":{"u":{"id":1,"name":"Ada"}},"crm_then_wrap":{"c":'.foldWrapJson().',"s":1}}')
        ->and(resourceProps(SpreadFoldCertainResource::class))->toBe([
            'crm_then_app' => '{ u: User }',
            'crm_then_wrap' => '{ c: AsEnum<typeof Status>; s: StatusType }',
        ])
        ->and(foldImportNames(SpreadFoldCertainResource::class))->toBe(['Status', 'StatusType', 'User']);
});

// D2: a later write that may leave the key unset joins the value before it, which PHP still sends where it does;
// every class either side names is kept and imported. An EnumResource wrap beside a same-named enum still folds into
// the wrap (`row_status`), as a ternary of the two does: a follow-up, not the join.
it('joins a key with a later write that may leave it unset, keeping both classes', function () {
    config()->set('ts-publish.enums.use_tolki_package', true);

    expect(foldSent(SpreadFoldMaybeResource::class))->toBe('{"u":{"id":1,"name":"Ada"},"by":{"id":1,"name":"Ada"},'
        .'"row_user":{"u":{"id":2,"name":"Cy"}},"row_status":{"c":'.foldWrapJson().'}}')
        ->and(foldSent(SpreadFoldMaybeResource::class, ['picked' => '1', 'flagged' => '1']))->toBe('{"u":{"id":2,"name":"Cy"},'
            .'"by":"hi","row_user":{"u":{"id":1,"name":"Ada"}},"row_status":{"c":"active"},"m":"hi"}')
        ->and(foldSent(SpreadFoldMaybeResource::class, ['flagged' => '1']))->toContain('"m":{"id":1,"name":"Ada"}')
        ->and(resourceProps(SpreadFoldMaybeResource::class))->toBe([
            'u' => 'WorkbenchUser | CrmUser',
            'by' => 'WorkbenchUser | string',
            'row_user' => '{ u: CrmUser | WorkbenchUser }',
            'row_status' => '{ c: AsEnum<typeof Status> }',
            'm?' => 'WorkbenchUser | string',
        ]);
});

// D3: a later when() value always sets the key, so the value before it is never sent; the key is optional.
it('publishes a key a later when() value writes as that value alone, optional', function () {
    $loaded = '"author":{"id":null,"name":"Ada","email":null,"role":null}';

    expect(spreadValueSent(SpreadFoldRemovableResource::class, ['picked' => '1']))->toBe('{"id":9,'.$loaded.','
        .'"writer":{"id":null,"name":"Ada","email":null,"role":null},"by":"hi","helper_by":"hi","local":"hi"}')
        ->and(spreadValueSent(SpreadFoldRemovableResource::class))->toBe('{"id":9,'.$loaded.'}')
        ->and(publishedResourceKeyTypes(SpreadFoldRemovableResource::class, Post::class))->toBe([
            'id' => 'number',
            'author?' => 'UserResource',
            'writer?' => 'UserResource',
            'by?' => 'string',
            'helper_by?' => 'string',
            'local?' => 'string',
        ])
        ->and(analyzedImportNames(SpreadFoldRemovableResource::class))->toBe(['UserResource']);
});

// D3 and D17: an Inertia optional() prop a helper writes over a prop sends that prop on a partial reload alone.
it('publishes a prop a helper writes again as an optional prop as that prop alone', function () {
    expect(inertiaPropsJson(new SpreadFoldPropsController()->show()))->toBe('[]')
        ->and(inertiaPropsJson(new SpreadFoldPropsController()->show(), partialReload('SpreadFold/Show', 'probe')))
        ->toBe('{"probe":"x"}')
        ->and(pageData(SpreadFoldPropsController::class.'@show')['pageType'])->toBe('Inertia.SharedData & { probe?: string }');
});

// D7, D8, D9, D11, D12: a key set before a merge beats it, a removed one stops it, a merge beats every later write,
// a merge's removed value lets the later one through, and the first merge that runs wins.
it('lifts each merge over the keys after it and under the keys before it, as Laravel\'s mergeData() does', function () {
    $ada = '{"id":1,"name":"Ada"}';

    expect(foldSent(SpreadFoldMergeOrderResource::class))->toBe('{"held":'.$ada.',"maybe_held":"hi","merged":"hi",'
        .'"beats_helper":"hi","dropped":'.$ada.',"twice":'.$ada.',"first":"hi"}')
        ->and(foldSent(SpreadFoldMergeOrderResource::class, ['picked' => '1']))->toBe('{"held":'.$ada.',"maybe_held":'.$ada.','
            .'"gone":'.$ada.',"merged":"hi","beats_helper":"hi","dropped":"hi","twice":"hi","first":"hi"}')
        ->and(resourceProps(SpreadFoldMergeOrderResource::class))->toBe([
            'held' => 'User',
            'maybe_held' => 'User | string',
            'gone?' => 'User',
            'merged' => 'string',
            'beats_helper' => 'string',
            'dropped' => 'string | User',
            'twice' => 'string | User',
            'first' => 'string',
        ]);
});

// D10: a mergeWhen() value wins wherever the merge runs, over a later write of any kind, which wins elsewhere.
it('publishes a key a mergeWhen() writes before a later write of any kind as either value', function () {
    $ada = '{"id":1,"name":"Ada"}';

    expect(foldSent(SpreadFoldMergeWhenResource::class))->toBe('{"id":1,"helper":'.$ada.',"local":'.$ada.','
        .'"literal":'.$ada.',"u":{"id":2,"name":"Cy"},"removable":'.$ada.',"unless":'.$ada.'}')
        ->and(foldSent(SpreadFoldMergeWhenResource::class, ['picked' => '1', 'flagged' => '1']))->toBe('{"id":1,'
            .'"helper":"hi","local":"hi","literal":"hi","u":'.$ada.',"removable":"hi","later_when":"hi"}')
        ->and(foldSent(SpreadFoldMergeWhenResource::class, ['flagged' => '1']))->toContain('"later_when":'.$ada)
        ->and(resourceProps(SpreadFoldMergeWhenResource::class))->toBe([
            'id' => 'number',
            'helper' => 'string | WorkbenchUser',
            'local' => 'string | WorkbenchUser',
            'literal' => 'string | WorkbenchUser',
            'u' => 'WorkbenchUser | CrmUser',
            'removable' => 'string | WorkbenchUser',
            'unless?' => 'WorkbenchUser',
            'later_when?' => 'string | WorkbenchUser',
        ]);
});

// D14: an open value's named key is written where it stands. Two of the table's residuals keep the earlier key's type:
// a key only the unnamed part re-sets (`zz`), and a map local given a named key the walk leaves untyped (`signature`).
it('publishes the named keys of a value that also holds unnamed ones as that value writes them', function () {
    expect(foldSent(SpreadFoldOpenValueResource::class))->toBe('{"open_literal":{"by":"hi","z":"q"},'
        .'"walked":{"by":"hi","z":"q"},"signature":{"by":"hi","z":1,"tone":"warm"},"helper":{"by":"hi","z":"q"},'
        .'"before_arm":{"by":"hi","z":"q","id":1,"name":"Ada","email":null,"role":null},'
        .'"literal_holding_local":{"by":"hi","x":1},"literal_of_local":{"by":"hi"},"local_of_spreads":{"by":"hi","id":1},'
        .'"top":"hi","top_id":1,"walked_top":"hi","zz":"q"}')
        ->and(resourceProps(SpreadFoldOpenValueResource::class))->toBe([
            'open_literal' => '{ by: string; z: number }',
            'walked' => '{ by: string; z: number }',
            'signature' => '{ by: User; z: number }',
            'helper' => '{ by: string; z: number }',
            'before_arm' => 'Omit<{ by: string }, keyof UserResource> & UserResource',
            'literal_holding_local' => '{ by: string; x: number }',
            'literal_of_local' => '{ by: string }',
            'local_of_spreads' => '{ by: string; id: number }',
            'top' => 'string',
            'top_id' => 'number',
            'walked_top' => 'string',
            'zz' => 'number',
        ]);
});

// D15: a value that is one of two closed arrays writes per key the arms' union, required where every arm holds it.
it('publishes each key a union of closed values writes as the union of its arms\' values', function () {
    expect(foldSent(SpreadFoldUnionValueResource::class))->toBe('{"ternary":{"by":1},"ternary_partial":{"by":2},'
        .'"array_merge":{"by":"hi"},"removable_or_empty":{"id":0,"by":{"id":1,"name":"Ada"}},"ternary_removable":{"id":0,"by":"q"}}')
        ->and(foldSent(SpreadFoldUnionValueResource::class, ['picked' => '1']))->toBe('{"ternary":{"by":"hi"},'
            .'"ternary_partial":{"by":"hi","x":1},"array_merge":{"by":"hi"},"removable_or_empty":{"id":0},"ternary_removable":{"id":0}}')
        ->and(foldSent(SpreadFoldUnionValueResource::class, ['picked' => '1', 'flagged' => '1']))
        ->toContain('"ternary_removable":{"id":0,"by":1}')
        ->and(resourceProps(SpreadFoldUnionValueResource::class))->toBe([
            'ternary' => '{ by: string | number }',
            'ternary_partial' => '{ by: string | number; x?: number }',
            'array_merge' => '{ by: string }',
            'removable_or_empty' => '{ id: number; by?: User | number }',
            'ternary_removable' => '{ id: number; by?: number | string }',
        ]);
});

// D16: each return folds on its own, then the returns join, a key one of them lacks optional.
it('folds each return on its own before the returns join', function () {
    $ada = '{"id":1,"name":"Ada"}';

    expect(foldSent(SpreadFoldReturnsResource::class))->toBe('{"n":1}')
        ->and(foldSent(SpreadFoldReturnsResource::class, ['flagged' => '1']))->toBe('{"id":1,"by":'.$ada.','
            .'"helper":'.$ada.',"key":'.$ada.',"merged":'.$ada.',"local":'.$ada.'}')
        ->and(foldSent(SpreadFoldReturnsResource::class, ['flagged' => '1', 'picked' => '1']))
        ->toBe('{"id":1,"by":"hi","helper":"hi","key":"hi","merged":"hi","local":"hi"}')
        ->and(resourceProps(SpreadFoldReturnsResource::class))->toBe([
            'id?' => 'number',
            'by?' => 'string | User',
            'helper?' => 'User | string',
            'key?' => 'User | string',
            'merged?' => 'string | User',
            'local?' => 'User | string',
            'n?' => 'number',
        ]);
});

// D13: resolve() sends only the keys it keeps, so a key it may drop leaves the earlier value where it does.
it('joins a key with a resolved resource\'s key that resource may drop, which leaves the earlier value there', function () {
    expect(spreadValueSent(SpreadFoldResolvedArmResource::class, ['picked' => '1']))->toBe('{"title":"hi"}')
        ->and(spreadValueSent(SpreadFoldResolvedArmResource::class))->toBe('{"title":9}')
        ->and(publishedResourceKeyTypes(SpreadFoldResolvedArmResource::class, Post::class))->toBe(['title' => 'number | string']);
});

// A `?` a method's `@return` declares says its key may be missing, never a removed value, so the value before it stays.
it('joins a key with a declared shape\'s optional key, which the earlier value fills where the shape leaves it out', function () {
    expect(foldSent(SpreadFoldDeclaredResource::class))->toBe('{"by":"hi","n":"x","row":{"by":"hi","n":"x"},'
        .'"static":{"n":"x","by":"hi"},"local":{"n":"x","by":"hi"}}')
        ->and(foldSent(SpreadFoldDeclaredResource::class, ['picked' => '1']))->toBe('{"by":"hi","n":1,'
            .'"row":{"by":"hi","n":1},"static":{"n":1,"by":"hi"},"local":{"n":1,"by":"hi"}}')
        ->and(resourceProps(SpreadFoldDeclaredResource::class))->toBe([
            'by' => 'string',
            'n' => 'string | number',
            'row' => '{ by: string; n: string | number }',
            'static' => '{ n: string | number; by: string }',
            'local' => '{ n: string | number; by: string }',
        ]);
});

it('joins a broadcast key and a page prop with a declared shape\'s optional key', function () {
    $payload = new BroadcastEventTransformer(SpreadFoldDeclaredEvent::class)->properties;
    $sent = static fn (string $pick): string => inertiaPropsJson(
        new SpreadFoldPropsController()->declared(Request::create('/', 'GET', ['pick' => $pick])),
    );

    expect(json_encode(new SpreadFoldDeclaredEvent(foldBox())->broadcastWith()))->toBe('{"n":"x","by":"hi"}')
        ->and(json_encode(new SpreadFoldDeclaredEvent(foldBox(), picked: true)->broadcastWith()))->toBe('{"n":1,"by":"hi"}')
        ->and(array_map(static fn (array $p): string => ($p['optional'] ? '?' : '').$p['type'], $payload))
        ->toBe(['n' => 'string | number', 'by' => 'string'])
        ->and($sent('s'))->toBe('{"probe":5}')
        ->and($sent('x'))->toBe('{"probe":"x"}')
        ->and(pageData(SpreadFoldPropsController::class.'@declared')['pageType'])
        ->toBe('Inertia.SharedData & { probe: number | string }');
});
