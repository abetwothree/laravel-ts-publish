<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KeyBuiltLocalGapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KeyBuiltLocalScopesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KeyBuiltLocalValueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MergeClosureGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MergeClosureHelperLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MergeClosureLocalKeyUnsetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MergeClosureLocalPlusResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MergeClosureShadowedValueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MergeClosureUnimportedLocalResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MergedLocalResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReadLocalHelperLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedBodylessParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedBranchLocalKeyUnsetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedBranchReplacedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedCastLoneVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedCastPartialHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedClassBranchesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedClassResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedCoalesceKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedConditionalUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedDeclinedBareSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedDeclinedSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedDeclinedStaticSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedDecliningParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedDecliningParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedDynamicKeyAloneResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedDynamicKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedDynamicUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedElseifChainResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedEnumKeyResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedEnumResourceBranchesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedEnumResourceResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedEnumSpreadResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedFilterThenAppendedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedFilterThenOpaqueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedForLoopResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedGuardedVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedGuardOnlyVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedHelperParameterKeyUnsetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedHelperVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedInterpolatedUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedJoinedKeyUnsetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedLocalKeyUnsetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedLocalOperandsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedLoopResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedMergeClosureVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedMergeVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedMixedUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedNestedKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedNestedLoopResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedNestedUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedNumericKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOnlyVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOpaqueBesideReadableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOpaqueHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOpaqueParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOpaqueVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOptionalResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedParameterKeyUnsetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedParentWithResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedPartialHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedPartialValueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedPartlyReadSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedPushedKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedReadableHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedReadableHelperVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedReferenceKeyUnsetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedRejectedHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedReplacedVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedRequiredResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSelfSpreadGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSelfSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedShapedLoneVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSignatureUnsetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSkippingParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSkippingParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedThrowingBranchResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedTryLiteralThenOpaqueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedUnionAssignResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedUnmodeledOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedUnmodeledParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedUnsetLiteralKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedUnsetParentKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedUnsetWithKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedVanishingKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedVanishingSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedWhileLoopResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\ReturnedVariableLinesService;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithReturnedVariable;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Http\Request;
use PhpParser\Node;
use Workbench\App\Enums\Status;
use Workbench\App\Events\ManifestAssembled;
use Workbench\App\Http\Resources\CategoryBranchesResource;
use Workbench\App\Http\Resources\PostBuiltValuesResource;
use Workbench\App\Http\Resources\ReturnedParentVariableResource;
use Workbench\App\Http\Resources\ReturnedVariableBranchesResource;
use Workbench\App\Http\Resources\ReturnedVariableResource;
use Workbench\App\Http\Resources\TagDefaultsResource;
use Workbench\App\Http\Resources\TagParentDefaultsResource;
use Workbench\App\Models\Category;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\App\Services\QuoteLinesService;

/**
 * The members a resource publishes, one `name: type` or `name?: type` per key, in order.
 *
 * @param  class-string  $class
 */
function returnedVariableMembers(string $class): string
{
    return collect((new ResourceTransformer($class))->properties)
        ->map(fn (array $p, string $name): string => $name.($p['optional'] ? '?' : '').': '.$p['type'])
        ->implode('; ');
}

/**
 * The members a resource publishes for the named keys, one `name: type` or `name?: type` per key, in order.
 *
 * @param  class-string  $class
 * @param  list<string>  $keys
 */
function returnedVariableMembersOf(string $class, array $keys): string
{
    return collect((new ResourceTransformer($class))->properties)
        ->only($keys)
        ->map(fn (array $p, string $name): string => $name.($p['optional'] ? '?' : '').': '.$p['type'])
        ->implode('; ');
}

/**
 * What Laravel sends for a post with the given attributes, under a request with the given query.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, string>  $query
 * @return array<array-key, mixed>
 */
function sentPostBuiltValues(array $attributes, array $query = []): array
{
    $post = new Post;
    $post->forceFill(['id' => 4, 'title' => 'Hello', ...$attributes]);

    return new PostBuiltValuesResource($post)->resolve(Request::create('/', 'GET', $query));
}

/**
 * The walk of `$d` over a method body given as source, read on a resource over Post.
 */
function walkedVariable(string $body): ResourceAnalysis
{
    $analyzer = new class(new ReflectionClass(ReturnedHelperVariableResource::class), Post::class) extends ResourceAstAnalyzer
    {
        /**
         * The walk of `$d` over a body.
         *
         * @param  array<Node\Stmt>  $stmts
         */
        public function walkD(array $stmts): ResourceAnalysis
        {
            return $this->resolveVariableReturnAnalysis($stmts, 'd');
        }
    };

    return $analyzer->walkD(resolve(AstParser::class)->parseSource('<?php '.$body));
}

/**
 * The members the walk of `$d` publishes over a method body given as source, one `name: type` per key, in order.
 */
function walkedMembers(string $body): string
{
    return implode('; ', array_map(
        fn (array $p): string => $p['name'].($p['optional'] ? '?' : '').': '.$p['type'],
        walkedVariable($body)->properties,
    ));
}

test('a toArray() that returns a variable publishes the keys its writes set', function () {
    expect(returnedVariableMembers(ReturnedVariableResource::class))
        ->toBe('id: number; name: string; slug?: string; posts_count?: number; quote: { unit: string; tax: number }');
});

test('a returned variable starts from any whole array the first-return fallback reads', function (string $class, string $expected) {
    expect(returnedVariableMembers($class))->toBe($expected);
})->with([
    'parent::toArray()' => [
        ReturnedParentVariableResource::class,
        'id: number; name: string; created_at: string | null; updated_at: string | null; display_name: string',
    ],
    '$this->only()' => [ReturnedOnlyVariableResource::class, 'id: number; name: string; label: string'],
    'array_merge()' => [ReturnedMergeVariableResource::class, 'id: number; name: string; label: string'],
    '$this->method()' => [ReturnedHelperVariableResource::class, 'id: number; name: string; label: string'],
    '+= keeps the key already set' => [ReturnedUnionAssignResource::class, 'id: number; name: string'],
    'a spread of itself, the later key winning' => [ReturnedSelfSpreadResource::class, 'id: number; name: number'],
    'a whole re-assignment, which drops the earlier keys' => [ReturnedReplacedVariableResource::class, 'name: string'],
]);

test('a returned variable is a branch beside a literal and a return [] guard', function () {
    expect(returnedVariableMembers(ReturnedVariableBranchesResource::class))->toBe('id?: number; name?: string');
});

test('a self-spread variable beside a return [] guard publishes its keys optional', function () {
    expect(returnedVariableMembers(ReturnedSelfSpreadGuardResource::class))->toBe('id?: string');
});

test('a key re-set to a value that can vanish publishes optional', function (string $class) {
    expect(returnedVariableMembers($class))->toBe('id: number; a?: Post[]');
})->with([
    'a merged whole-array write' => [ReturnedVanishingSpreadResource::class],
    'a key write' => [ReturnedVanishingKeyResource::class],
]);

test('a key re-set unconditionally to a value that cannot vanish stays required', function () {
    expect(returnedVariableMembers(ReturnedRequiredResetResource::class))->toBe('a: string');
});

test('a key re-set conditionally keeps an optional old value optional, beside the new one', function () {
    expect(returnedVariableMembers(ReturnedOptionalResetResource::class))->toBe('a?: Post[] | number');
});

test('a re-set key keeps only the last write\'s channel', function (string $class) {
    // The transformer imports only the tokens a type spells, so the enum channel itself is what a stale entry shows in.
    $analysis = (new ResourceAstAnalyzer(new ReflectionClass($class), Post::class))->analyze();
    $transformer = new ResourceTransformer($class);

    expect($analysis->hasFqcnChannel('status'))->toBeFalse()
        ->and(returnedVariableMembers($class))->toBe('id: number; status: string')
        ->and([...$transformer->typeImports, ...$transformer->valueImports])->toBe([]);
})->with([
    'a merged whole-array write' => [ReturnedEnumSpreadResetResource::class],
    'a key write' => [ReturnedEnumKeyResetResource::class],
]);

test('a variable the walk cannot read is skipped, so the literal branch keeps its keys required', function () {
    expect(returnedVariableMembers(ReturnedOpaqueVariableResource::class))->toBe('id: number; name: string');
});

test('a base read as nothing never turns a literal\'s keys optional, and adds the variable\'s own keys optional', function (string $class) {
    expect(returnedVariableMembers($class))->toBe('id: number; x?: number');
})->with([
    'parent::toArray() with no model' => [ReturnedUnmodeledParentResource::class],
    'only() with no model' => [ReturnedUnmodeledOnlyResource::class],
]);

test('a base not read completely never turns a literal\'s keys optional, and adds the variable\'s own keys optional', function (string $class, string $members) {
    expect(returnedVariableMembers($class))->toBe($members);
})->with([
    'a helper returning the model\'s toArray()' => [ReturnedOpaqueHelperResource::class, 'id: number; name: string; extra?: boolean'],
    'a helper building on the model\'s toArray()' => [
        ReturnedPartialHelperResource::class, 'id: number; name: string; kind?: string; extra?: boolean',
    ],
    'a parent whose own toArray() it cannot read' => [ReturnedOpaqueParentChildResource::class, 'id: number; name: string; extra?: boolean'],
    'a helper whose own variable the gate rejects' => [ReturnedRejectedHelperResource::class, 'id: number; name: string; extra?: boolean'],
]);

test('a helper base read completely still makes the variable a branch', function (string $class) {
    expect(returnedVariableMembers($class))->toBe('id: number; name: string; extra?: boolean');
})->with([
    'a helper returning a literal' => [ReturnedReadableHelperResource::class],
    'a helper returning a readable variable' => [ReturnedReadableHelperVariableResource::class],
]);

test('a value read only partly still leaves the variable a branch', function () {
    expect(returnedVariableMembers(ReturnedPartialValueResource::class))
        ->toBe('id: number; name: string; meta?: { kind: string }');
});

test('a variable with a write the walk does not read never turns a literal\'s keys optional', function (string $class) {
    expect(returnedVariableMembers($class))->toBe('id: number; name: string');
})->with([
    'a dynamic key' => [ReturnedDynamicKeyResource::class],
    'an appended key' => [ReturnedPushedKeyResource::class],
    'a nested key' => [ReturnedNestedKeyResource::class],
    'a ??= of a key it does not hold' => [ReturnedCoalesceKeyResource::class],
]);

// A written int key is read as resolveKeyName() reads a literal's; beside a named key, a resource keeps it.
test('a variable write to an int or int-constant key publishes that key', function () {
    expect(array_map(fn (array $property): string => $property['type'], new ResourceTransformer(ReturnedNumericKeyResource::class)->properties))
        ->toBe(['id' => 'number', 42 => 'string', 3 => 'boolean']);
});

test('a variable with an unset() key is a branch beside the literal, so a key either one lacks publishes optional', function () {
    expect(returnedVariableMembers(ReturnedUnsetKeyResource::class))->toBe('id: number; name?: string; slug?: string');
});

test('an unset() on a parent::toArray() base drops the key and its import channel', function () {
    $analysis = (new ResourceAstAnalyzer(new ReflectionClass(ReturnedUnsetParentKeyResource::class), Tag::class))->analyze();

    expect(returnedVariableMembers(ReturnedUnsetParentKeyResource::class))
        ->toBe('id: number; name: string; slug: string; created_at: string | null; updated_at: string | null; products?: Product[]')
        ->and($analysis->hasFqcnChannel('posts'))->toBeFalse();
});

test('an unset() on a literal base drops the key', function () {
    expect(returnedVariableMembers(ReturnedUnsetLiteralKeyResource::class))->toBe('id: number; name: string');
});

test('an unset() on a parent::with() base drops the key', function () {
    $published = array_map(
        fn (array $p): string => $p['name'].($p['optional'] ? '?' : '').': '.$p['type'],
        resolve(AstEngine::class)->analyze(ReturnedUnsetWithKeyResource::class, 'with')->properties,
    );

    expect($published)->toBe(['version: string']);
});

test('an unset() inside an if makes the key optional', function () {
    expect(returnedVariableMembers(ReturnedConditionalUnsetKeyResource::class))->toBe('id: number; name?: string');
});

test('an unset() of a dynamic or nested key keeps the variable skipped', function (string $class, string $expected) {
    expect(returnedVariableMembers($class))->toBe($expected);
})->with([
    'a dynamic key' => [ReturnedDynamicUnsetKeyResource::class, 'id: number; name: string; slug?: string'],
    'an interpolated key, which can name any key' => [ReturnedInterpolatedUnsetKeyResource::class, 'id: number; name: string; slug?: string'],
    'a nested key' => [ReturnedNestedUnsetKeyResource::class, 'id: number; meta: { name: string }'],
]);

test('a variable with a write the walk cannot name still publishes what the walk reads when returned alone', function () {
    expect(returnedVariableMembers(ReturnedDynamicKeyAloneResource::class))->toBe('id: number');
});

test('a lone variable read leniently keeps the method\'s casts and @return shape', function (string $class, string $expected) {
    expect(returnedVariableMembers($class))->toBe($expected);
})->with([
    'method-level #[TsCasts]' => [
        ReturnedCastLoneVariableResource::class,
        'id: number; meta: Record<string, string>; injected: number',
    ],
    'the method\'s @return shape' => [ReturnedShapedLoneVariableResource::class, 'id: number; meta: string'],
    'a cast over a helper base not read completely' => [ReturnedCastPartialHelperResource::class, 'meta: Record<string, string>'],
]);

test('a variable beside only a return [] guard is read leniently as a branch', function () {
    expect(returnedVariableMembers(ReturnedGuardOnlyVariableResource::class))->toBe('id?: number');
});

test('a skipped variable beside a return the sweep does not take leaves the first return read', function (string $class) {
    expect(returnedVariableMembers($class))->toBe('id: number; name: string');
})->with([
    'a filter, then a variable on an unreadable base' => [ReturnedFilterThenOpaqueResource::class],
    'a filter, then a variable with an appended key' => [ReturnedFilterThenAppendedResource::class],
    'a literal inside try, then a variable on an unreadable base' => [ReturnedTryLiteralThenOpaqueResource::class],
]);

test('a skipped variable beside one read completely leaves that one its required keys', function () {
    expect(returnedVariableMembers(ReturnedOpaqueBesideReadableResource::class))->toBe('id: number; name: string; y: number; x?: number');
});

test('a return a parent\'s sweep skips makes a child variable built on it skip, and leaves the parent\'s own shape', function (string $parent, string $parentShape, string $child) {
    expect(returnedVariableMembers($parent))->toBe($parentShape)
        ->and(returnedVariableMembers($child))->toBe('id: number; name: string; extra?: boolean');
})->with([
    'a return beside its literal' => [
        ReturnedSkippingParentResource::class, 'id: number', ReturnedSkippingParentChildResource::class,
    ],
    'a return the first-return fallback leaves unread' => [
        ReturnedDecliningParentResource::class, '', ReturnedDecliningParentChildResource::class,
    ],
]);

test('a parent delegating to no model makes a child variable built on it skip', function () {
    expect(returnedVariableMembers(ReturnedBodylessParentChildResource::class))->toBe('id: number; name: string; extra?: boolean');
});

// At a JsonResource, parent::with() is the `$with` default and parent::jsonSerialize() the subject's toArray().
test('a parent call to a method other than toArray() over a JsonResource publishes none of the model\'s keys', function (string $method, array $members) {
    $published = array_map(
        fn (array $p): string => $p['name'].($p['optional'] ? '?' : '').': '.$p['type'],
        resolve(AstEngine::class)->analyze(ReturnedParentWithResource::class, $method)->properties,
    );

    expect($published)->toBe($members);
})->with([
    'a variable built on parent::with()' => ['with', ['extra: string']],
    'a return of parent::jsonSerialize()' => ['jsonSerialize', ['id: number']],
]);

test('the body fallback types a with() built on parent::with() by the keys it writes', function () {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(ReturnedParentWithResource::class, 'with')['type'] ?? null)
        ->toBe('{ extra: string }');
});

test('a returned variable is a branch only when the walk reads every whole write to it', function (string $body, bool $reads) {
    $analyzer = new class(new ReflectionClass(ReturnedHelperVariableResource::class), Tag::class) extends ResourceAstAnalyzer
    {
        /**
         * Whether the branch gate reads `$d` in a body that seeds it with nothing.
         *
         * @param  array<Node>  $stmts
         */
        public function readsD(array $stmts): bool
        {
            return $this->readsVariableArray($stmts, 'd', null);
        }
    };

    expect($analyzer->readsD(resolve(AstParser::class)->parseSource('<?php '.$body)))->toBe($reads);
})->with([
    'a literal base, whose key writes never count against it' => ['$d = ["a" => 1]; $d["b"] = 2; $d["n"] += 1; $d["n"]++;', true],
    'a += of an array the walk reads' => ['$d = ["a" => 1]; $d += ["b" => 2];', true],
    'the own model only() with literal keys' => ['$d = $this->only(["id"]);', true],
    'the subject\'s own helper' => ['$d = $this->basics();', true],
    'a += and no assignment' => ['$d += ["b" => 2];', false],
    'an opaque base' => ['$d = $this->resource->toArray();', false],
    'only() with runtime keys' => ['$d = $this->only($keys);', false],
    'a helper the subject lacks' => ['$d = $this->noSuchHelper();', false],
    'a .= write' => ['$d = ["a" => 1]; $d .= "x";', false],
    'a foreach target' => ['$d = ["a" => 1]; foreach ($rows as $d) {}', false],
    'a reference' => ['$d = ["a" => 1]; $e = &$d;', false],
    'a closure that uses it by reference' => ['$d = ["a" => 1]; $f = function () use (&$d) {};', false],
]);

test('a key written in a try, catch or switch body publishes optional', function () {
    expect(returnedVariableMembers(ReturnedGuardedVariableResource::class))
        ->toBe('id: number; slug?: string; failed?: boolean; color?: string | null');
});

test('a broadcastWith() that returns a variable publishes its keys', function () {
    $properties = (new BroadcastEventTransformer(ManifestAssembled::class))->properties;

    expect(array_map(fn (array $p): string => ($p['optional'] ? '?' : '').$p['type'], $properties))
        ->toBe(['parcelId' => 'number', 'carrier' => 'string', 'priority' => '?string']);
});

test('a share() that returns a variable publishes its keys', function () {
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn(MiddlewareWithReturnedVariable::class);

    expect($analyzer->analyze()['sharedPageProps'] ?? null)->toBe('{ appName: string, userId?: number }');
});

test('the body fallback reads a vague helper that returns a variable, and reads a mixed one as a value', function (string $class, string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve($class, $method)['type'] ?? null)->toBe($expected);
})->with([
    'a static helper' => [ReturnedVariableLinesService::class, 'lines', '{ unit: string; tax: number }'],
    'a container instance' => [QuoteLinesService::class, 'lines', '{ unit: string; tax: number }'],
    'a literal beside a variable' => [ReturnedVariableLinesService::class, 'mixedLines', '{ unit: string } | { unit: string; tax: number }'],
]);

test('a spread the analyzer declines leaves the variable not read completely, so the literal keeps its keys', function (string $class) {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php']);
    $sent = fn (bool $compact): array => new $class($tag)
        ->resolve(Request::create('/', 'GET', ['compact' => $compact ? '1' : '0']));

    expect(returnedVariableMembers($class))->toBe('id: number; name: string')
        ->and($sent(true))->toBe(['id' => 9, 'name' => 'php'])
        ->and($sent(false))->toBe(['id' => 9, 'name' => 'php']);
})->with([
    'a $this-> spread' => [ReturnedDeclinedSpreadResource::class],
    'a self:: spread' => [ReturnedDeclinedStaticSpreadResource::class],
    'a bare call spread' => [ReturnedDeclinedBareSpreadResource::class],
]);

test('a spread resource read only partly leaves a variable built on it a branch, so a key only the literal sets is optional', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php']);
    $sent = fn (bool $compact): array => new ReturnedPartlyReadSpreadResource($tag)
        ->resolve(Request::create('/', 'GET', ['compact' => $compact ? '1' : '0']));

    expect(returnedVariableMembers(ReturnedPartlyReadSpreadResource::class))->toBe('id: number; extra?: number')
        ->and($sent(true))->toBe(['id' => 9, 'extra' => 1])
        ->and($sent(false))->toBe(['id' => 9, 'name' => 'php']);
});

test('a key the branches of an if chain write holds each branch\'s value, required when every branch writes it', function () {
    expect(returnedVariableMembers(CategoryBranchesResource::class))->toBe(
        'id: number; label: number | string; mode: number | string; top: CategoryResource | null; '
        .'tier: string | number | boolean; first_only?: boolean; described?: boolean',
    );
});

test('Laravel sends each required key of an if chain on every path, with a value its type admits', function () {
    $parent = new Category;
    $parent->forceFill(['id' => 1, 'name' => 'Root', 'slug' => 'root', 'is_active' => true, 'sort_order' => 1, 'description' => 'd']);
    $parent->setRelation('parent', null);
    $second = new Category;
    $second->forceFill(['id' => 2, 'name' => 'A', 'slug' => 'a', 'is_active' => false, 'sort_order' => 2]);
    $second->setRelation('parent', $parent);
    $third = new Category;
    $third->forceFill(['id' => 3, 'name' => 'B', 'slug' => 'b', 'is_active' => false, 'sort_order' => 3]);
    $third->setRelation('parent', null);

    $sent = array_map(
        fn (Category $category): array => json_decode(json_encode(new CategoryBranchesResource($category)->resolve(Request::create('/'))), true),
        [$parent, $second, $third],
    );

    expect(array_column($sent, 'mode'))->toBe([1, 'off', 'off'])
        ->and(array_map(fn (array $row): bool => $row['top'] === null, $sent))->toBe([true, false, true])
        ->and(array_column($sent, 'tier'))->toBe(['first', 2, false])
        ->and(array_column($sent, 'label'))->toBe(['Root', 2, 3])
        ->and(array_map(fn (array $row): array => array_keys($row), $sent))->toBe([
            ['id', 'label', 'mode', 'top', 'tier', 'first_only', 'described'],
            ['id', 'label', 'mode', 'top', 'tier'],
            ['id', 'label', 'mode', 'top', 'tier'],
        ]);
});

test('a whole write in one branch of an if chain replaces the keys there', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php', 'slug' => 'php-slug']);
    $sent = fn (bool $slim): array => new ReturnedBranchReplacedResource($tag)
        ->resolve(Request::create('/', 'GET', ['slim' => $slim ? '1' : '0']));

    expect(returnedVariableMembers(ReturnedBranchReplacedResource::class))->toBe('id?: number; name?: string; slug?: string')
        ->and($sent(false))->toBe(['id' => 9, 'name' => 'php'])
        ->and($sent(true))->toBe(['slug' => 'php-slug']);
});

test('a key a loop re-sets keeps the value it held before as one more arm', function () {
    $child = new Category;
    $child->forceFill(['id' => 5]);
    $sent = function (array $children): array {
        $category = new Category;
        $category->forceFill(['id' => 4]);
        $category->setRelation('children', $category->newCollection($children));

        return new ReturnedLoopResetResource($category)->resolve(Request::create('/'));
    };

    expect(returnedVariableMembers(ReturnedLoopResetResource::class))->toBe('id: number; mode: number | string; child?: number')
        ->and($sent([]))->toBe(['id' => 4, 'mode' => 1])
        ->and($sent([$child]))->toBe(['id' => 4, 'mode' => 'nested', 'child' => 5]);
});

test('a value a branch cannot type drops beside a typed one, and a lone null stays, as a ternary\'s arms do', function (string $body, string $members) {
    expect(walkedMembers($body))->toBe($members);
})->with([
    'beside a value held before' => ['$d = ["a" => 1]; if ($x) { $d["a"] = $nowhere; }', 'a: number'],
    'beside a lone null' => ['$d = []; if ($x) { $d["b"] = null; } else { $d["b"] = $nowhere; }', 'b: null'],
    'in every branch' => ['$d = []; if ($x) { $d["c"] = $nowhere; } else { $d["c"] = $nowhere; }', 'c: unknown'],
]);

// The arm left out is the last write of its key in the branch, or before the branches for the keys held before them.
test('the join records each branch value it leaves out, a value held before the branches included', function (string $body, array $dropped) {
    DroppedUnionArms::start();

    try {
        walkedVariable($body);
    } finally {
        $recorded = DroppedUnionArms::stop();
    }

    expect(array_map(fn (array $arm): string => $arm['expression'].' '.$arm['site'], $recorded))->toBe($dropped);
})->with([
    'a branch' => ['$d = ["a" => 1]; if ($x) { $d["a"] = $nowhere; }', ['$nowhere walk-branch-join']],
    'a key held before' => ['$d = []; $d["a"] = $nowhere; if ($x) { $d["a"] = 1; }', ['$nowhere walk-branch-join']],
    'an item held before' => ['$d = ["a" => $nowhere]; foreach ($rows as $row) { $d["a"] = 1; }', ['$nowhere walk-branch-join']],
    'the last write in a branch' => [
        '$d = []; if ($x) { $d["a"] = $first; $d["a"] = $last; } else { $d["a"] = 1; }',
        ['$last walk-branch-join'],
    ],
    'every branch' => ['$d = []; if ($x) { $d["c"] = $nowhere; } else { $d["c"] = $nowhere; }', []],
    'never a sibling branch\'s write' => [
        '$d = []; $d["a"] = $first; if ($x) { $d["a"] = $other; } else { if ($y) { $d["a"] = null; } }',
        ['$first walk-branch-join', '$other walk-branch-join'],
    ],
]);

test('a branch that throws leaves the join, so Laravel sends the key only as the other branch writes it', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9]);
    $sent = fn (bool $fail): array => new ReturnedThrowingBranchResource($tag)
        ->resolve(Request::create('/', 'GET', ['fail' => $fail ? '1' : '0']));

    expect(returnedVariableMembers(ReturnedThrowingBranchResource::class))->toBe('id: number; a: string')
        ->and($sent(false))->toBe(['id' => 9, 'a' => 'x'])
        ->and(fn () => $sent(true))->toThrow(RuntimeException::class, 'Unavailable.');
});

test('a body that ends in a throw or a return of another value leaves the join, and one returning the variable stays', function (string $body, string $members) {
    expect(walkedMembers($body))->toBe($members);
})->with([
    'a throw' => ['$d = ["id" => 1]; if ($x) { $d["a"] = 1; throw new RuntimeException(); } else { $d["a"] = "x"; }', 'id: number; a: string'],
    'a return of another value' => ['$d = ["id" => 1]; if ($x) { $d["a"] = 1; return []; } else { $d["a"] = "x"; }', 'id: number; a: string'],
    'a throw with no else' => ['$d = ["id" => 1]; if ($x) { $d["a"] = 1; throw new RuntimeException(); }', 'id: number'],
    'a loop body that throws' => ['$d = ["id" => 1]; foreach ($rows as $row) { $d["a"] = 1; throw new RuntimeException(); }', 'id: number'],
    'every body' => ['$d = ["id" => 1]; if ($x) { $d["a"] = 1; throw new RuntimeException(); } else { return []; }', 'id: number'],
    'a return of the variable' => ['$d = ["id" => 1]; if ($x) { $d["a"] = 1; return $d; } else { $d["a"] = "x"; }', 'id: number; a: number | string'],
]);

test('each branch keeps the class it types a key with, so every class a merged key spells is imported', function (string $class, string $members) {
    $imports = collect(new ResourceTransformer($class)->typeImports)->flatten()->sort()->values()->all();

    expect(returnedVariableMembers($class))->toBe($members)
        ->and($imports)->toBe(['Category', 'CategoryResource', 'PriorityType', 'StatusType', 'User', 'UserResource']);
})->with([
    'a variable a branch re-sets' => [
        ReturnedClassResetResource::class,
        'id: number; x: UserResource | CategoryResource | null; s: StatusType | PriorityType | null; who: User | Category | null',
    ],
    'two returned literals' => [
        ReturnedClassBranchesResource::class,
        'id: number; x: CategoryResource | UserResource | null; s: PriorityType | StatusType | null; who: Category | User | null',
    ],
]);

test('a body that may not run keeps the value a key held before as one more arm, as Laravel sends it', function (string $class, string $members, array $paths) {
    $tag = new Tag;
    $tag->forceFill(['id' => 9]);
    $sent = fn (array $query): array => new $class($tag)->resolve(Request::create('/', 'GET', $query));

    expect(returnedVariableMembers($class))->toBe($members)
        ->and(array_map($sent, array_column($paths, 0)))->toBe(array_column($paths, 1));
})->with([
    'a for loop' => [ReturnedForLoopResetResource::class, 'id: number; mode: number | string; seen?: boolean', [
        [['n' => '0'], ['id' => 9, 'mode' => 1]],
        [['n' => '2'], ['id' => 9, 'mode' => 'many', 'seen' => true]],
    ]],
    'a while loop' => [ReturnedWhileLoopResetResource::class, 'id: number; mode: number | string; seen?: boolean', [
        [['n' => '0'], ['id' => 9, 'mode' => 1]],
        [['n' => '2'], ['id' => 9, 'mode' => 'many', 'seen' => true]],
    ]],
    'a loop inside a loop' => [ReturnedNestedLoopResetResource::class, 'id: number; mode: number | string; seen?: boolean', [
        [['n' => '0', 'm' => '1'], ['id' => 9, 'mode' => 1]],
        [['n' => '2', 'm' => '0'], ['id' => 9, 'mode' => 1]],
        [['n' => '1', 'm' => '1'], ['id' => 9, 'mode' => 'deep', 'seen' => true]],
    ]],
    'an if/elseif chain with no else' => [ReturnedElseifChainResource::class, 'id: number; tier: number | string | boolean; first_only?: boolean', [
        [['tier' => 'a'], ['id' => 9, 'tier' => 'first', 'first_only' => true]],
        [['tier' => 'b'], ['id' => 9, 'tier' => false]],
        [[], ['id' => 9, 'tier' => 0]],
    ]],
]);

test('each branch keeps the enum it wraps a key in, so the merged key wraps each enum and imports it', function (string $class, string $type) {
    $transformer = new ResourceTransformer($class);

    expect($transformer->properties['s']['type'])->toBe($type)
        ->and(collect($transformer->valueImports)->flatten()->sort()->values()->all())->toBe(['Priority', 'Status'])
        ->and($transformer->typeImports)->toBe([]);
})->with([
    'a variable a branch re-sets' => [ReturnedEnumResourceResetResource::class, 'AsEnum<typeof Status> | AsEnum<typeof Priority> | null'],
    'two returned literals' => [ReturnedEnumResourceBranchesResource::class, 'AsEnum<typeof Priority> | AsEnum<typeof Status> | null'],
]);

test('a key\'s queued classes hold one copy per write of it, however many joins it passes', function (string $body, int $writes, bool $author) {
    $walk = walkedVariable($body);

    expect($walk->inlineEnumFqcns['meta'] ?? null)->toBe(array_fill(0, $writes, Status::class))
        ->and($walk->inlineModelFqcns['meta'] ?? null)->toBe($author ? [User::class] : null);
})->with([
    'no branch rewrites it' => [
        '$d = ["meta" => ["status" => $this->status, "author" => $this->author]]; '.implode(' ', array_map(
            fn (int $i): string => "if (\$x{$i}) { \$d['k{$i}'] = {$i}; } else { \$d['k{$i}'] = 'v'; }",
            range(1, 4),
        )).' foreach ($rows as $row) { $d["n"] = 1; }',
        1,
        true,
    ],
    'the first body of each chain rewrites it, and the later ones keep it' => [
        '$d = ["meta" => ["status" => $this->status]]; '.implode(' ', array_map(
            fn (int $i): string => "if (\$x{$i}) { \$d['meta'] = ['status' => \$this->status, 'k' => {$i}]; } "
                ."elseif (\$y{$i}) { \$d['p{$i}'] = 1; } else { \$d['q{$i}'] = 1; }",
            range(1, 4),
        )),
        5,
        false,
    ],
]);

test('a whole write that always runs in a branch replaces an unread write before it', function (string $body, string $keys, bool $complete) {
    $walk = walkedVariable($body);

    expect(implode(', ', array_map(fn (array $p): string => $p['name'].($p['optional'] ? '?' : ''), $walk->properties)))->toBe($keys)
        ->and($walk->readsCompletely)->toBe($complete);
})->with([
    'every branch replaces it' => ['$d = ["a" => 1]; $d[$k] = 2; if ($x) { $d = ["b" => 1]; } else { $d = ["c" => 1]; }', 'b?, c?', true],
    'one branch keeps it' => ['$d = ["a" => 1]; $d[$k] = 2; if ($x) { $d = ["b" => 1]; }', 'a?, b?', false],
    'a write that may not run merges into it' => ['$d = ["a" => 1]; $d[$k] = 2; try { $d = ["b" => 1]; } catch (Throwable) {}', 'a, b?', false],
]);

test('a whole return of + with a local operand publishes both sides\' keys', function (string $class, string $members, array $sent) {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php']);

    expect(returnedVariableMembers($class))->toBe($members)
        ->and(new $class($tag)->resolve(Request::create('/')))->toBe($sent);
})->with([
    'a literal + a local, the left id winning' => [
        TagDefaultsResource::class, 'id: number; note: string', ['id' => 9, 'note' => 'none'],
    ],
    'parent::toArray() + a local' => [
        TagParentDefaultsResource::class,
        'id: number; name: string; slug: string; color: string | null; created_at: string | null; '
        .'updated_at: string | null; posts?: Post[]; products?: Product[]; note: string',
        ['id' => 9, 'name' => 'php', 'note' => 'none'],
    ],
]);

test('a local written once reads as its array where a variable starts from it or adds it, and where a merge reads it', function (string $class, string $members, array $sent) {
    $tag = new Tag;
    $tag->forceFill(['id' => 9]);

    expect(returnedVariableMembers($class))->toBe($members)
        ->and(new $class($tag)->resolve(Request::create('/')))->toBe($sent);
})->with([
    'a variable\'s base and its +=' => [
        ReturnedLocalOperandsResource::class, 'note: string; id: number; flag: boolean', ['note' => 'none', 'id' => 9, 'flag' => true],
    ],
    'merge()' => [MergedLocalResource::class, 'id: number; note: string', ['id' => 9, 'note' => 'none']],
]);

// A closure sees an outer local only through `use`, and a write of its own replaces it. The engine still reads one a
// merge closure neither imports nor writes, through AnalysisScope::literalWriteAt(), but Laravel throws `Undefined
// variable` there.
test('a merge closure adds with + a local it imports, and its own local in place of the method\'s of that name', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php', 'slug' => 'php-slug']);

    expect(returnedVariableMembers(MergeClosureLocalPlusResource::class))
        ->toBe('id: number; name: string; note: string; slug: string; flag: boolean')
        ->and(new MergeClosureLocalPlusResource($tag)->resolve(Request::create('/')))
        ->toBe(['id' => 9, 'name' => 'php', 'note' => 'none', 'slug' => 'php-slug', 'flag' => true]);
});

test('a merge closure adds with + an outer local it neither imports nor writes, where Laravel throws', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php']);

    expect(returnedVariableMembers(MergeClosureUnimportedLocalResource::class))->toBe('id: number; name: string; note: string')
        ->and(fn () => new MergeClosureUnimportedLocalResource($tag)->resolve(Request::create('/')))
        ->toThrow(ErrorException::class, 'Undefined variable $extra');
});

test('a spread helper reads its own locals inside a merge closure, and while its caller reads a local of the same name', function (string $class, string $members, array $sent) {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php', 'slug' => 'php']);

    expect(returnedVariableMembers($class))->toBe($members)
        ->and(new $class($tag)->resolve(Request::create('/')))->toBe($sent);
})->with([
    'inside a merge closure' => [
        MergeClosureHelperLocalsResource::class, 'id: number; note: string; name: string', ['id' => 9, 'note' => 'none', 'name' => 'php'],
    ],
    'while its caller reads $defaults' => [
        ReadLocalHelperLocalsResource::class, 'id: number; note: string; x: number', ['id' => 9, 'note' => 'none', 'x' => 1],
    ],
]);

test('a mixed unset() removes each literal key and leaves only the unnamed one unread', function () {
    $mixed = walkedVariable('$d = ["a" => 1, "b" => 2, "c" => 3]; unset($d["a"], $d[$k]);');
    $named = walkedVariable('$d = ["a" => 1, "b" => 2, "c" => 3]; unset($d["a"], $d["b"]);');

    expect(returnedVariableMembers(ReturnedMixedUnsetKeyResource::class))->toBe('id: number; slug: string')
        ->and(array_column($mixed->properties, 'name'))->toBe(['b', 'c'])
        ->and($mixed->readsCompletely)->toBeFalse()
        ->and(array_column($named->properties, 'name'))->toBe(['c'])
        ->and($named->readsCompletely)->toBeTrue();
});

test('an unset() of a key a literal local holds removes that key', function (string $class, string $members, array $paths) {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php', 'slug' => 'php']);
    $sent = fn (array $query): array => new $class($tag)->resolve(Request::create('/', 'GET', $query));

    expect(returnedVariableMembers($class))->toBe($members)
        ->and(array_map($sent, array_column($paths, 0)))->toBe(array_column($paths, 1));
})->with([
    'beside a literal' => [ReturnedLocalKeyUnsetResource::class, 'id: number; name?: string', [
        [['compact' => '1'], ['id' => 9, 'name' => 'php']],
        [['compact' => '0'], ['id' => 9]],
    ]],
    'inside an if branch' => [ReturnedBranchLocalKeyUnsetResource::class, 'id: number; name?: string; slug?: string', [
        [['slim' => '0'], ['id' => 9, 'name' => 'php', 'slug' => 'php']],
        [['slim' => '1'], ['id' => 9]],
    ]],
    // The second closure returns alone a variable an unset() it cannot name may shrink, so it merges none of its keys.
    'in a merge closure that imports it, beside one whose own local shadows it' => [MergeClosureLocalKeyUnsetResource::class, 'id: number; name: string', [
        [['hide' => ''], ['id' => 9, 'name' => 'php', 'color' => null, 'label' => 'php']],
        [['hide' => 'color'], ['id' => 9, 'name' => 'php', 'label' => 'php']],
    ]],
    // A local that may hold another value at the unset() names no key, so the variable stays skipped.
    'a local an if may rewrite names no key' => [ReturnedJoinedKeyUnsetResource::class, 'id: number; name: string; slug?: string', [
        [['compact' => '1'], ['id' => 9, 'name' => 'php']],
        [['n' => '0'], ['id' => 9]],
        [['n' => '1'], ['id' => 9, 'slug' => 'php']],
    ]],
    'a local a reference rewrites names no key' => [ReturnedReferenceKeyUnsetResource::class, 'id: number; name: string; slug?: string', [
        [['compact' => '1'], ['id' => 9, 'name' => 'php']],
        [['compact' => '0'], ['id' => 9, 'slug' => 'php']],
    ]],
    'a parameter names no key' => [ReturnedParameterKeyUnsetResource::class, 'id: number; name: string; slug?: string', [
        [['compact' => '1'], ['id' => 9, 'name' => 'php']],
        [['compact' => '0'], ['id' => 9]],
    ]],
    'a parameter written once only after the unset() names no key' => [
        ReturnedHelperParameterKeyUnsetResource::class, 'id: number; name: string; slug: string', [[[], ['id' => 9, 'slug' => 'php']]],
    ],
]);

// Every signature entry admits `undefined`, so the entry that can still name the unset key stays sound.
test('an unset() of a literal key keeps a signature entry that can name it, which admits undefined', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php', 'slug' => 'php']);
    $sent = fn (string $suffix): array => new ReturnedSignatureUnsetResource($tag)
        ->resolve(Request::create('/', 'GET', ['suffix' => $suffix]));

    expect(returnedVariableMembers(ReturnedSignatureUnsetResource::class))
        ->toBe('id: number; [key: `slug${string}`]: string | undefined')
        ->and($sent(''))->toBe(['id' => 9])
        ->and($sent('_x'))->toBe(['id' => 9, 'slug_x' => 'php']);
});

test('a closure in value position that builds a variable by key writes publishes the keys', function () {
    $published = ['published_at' => now()];

    expect(returnedVariableMembersOf(PostBuiltValuesResource::class, ['built', 'branched']))
        ->toBe('built?: { id: number; title: string }; branched?: { id: number; flag: string | number }')
        ->and(sentPostBuiltValues([...$published, 'is_pinned' => true]))->toMatchArray([
            'built' => ['id' => 4, 'title' => 'Hello'],
            'branched' => ['id' => 4, 'flag' => 'pinned'],
        ])
        ->and(sentPostBuiltValues([...$published, 'is_pinned' => false])['branched'])->toBe(['id' => 4, 'flag' => 0])
        ->and(array_keys(sentPostBuiltValues(['published_at' => null])))->toBe(['key_written', 'unset_beside']);
});

test('a helper read as a value with a multi-write returned variable beside literal returns publishes each shape', function () {
    expect(returnedVariableMembersOf(PostBuiltValuesResource::class, ['key_written', 'unset_beside']))
        ->toBe('key_written: { id: number } | { id: number; title: string }; unset_beside: { id: number }')
        ->and(sentPostBuiltValues(['published_at' => null]))
        ->toBe(['key_written' => ['id' => 4, 'title' => 'Hello'], 'unset_beside' => ['id' => 4]])
        ->and(sentPostBuiltValues(['published_at' => null], ['short' => '1']))
        ->toBe(['key_written' => ['id' => 4], 'unset_beside' => ['id' => 4]]);
});

test('a merge closure\'s lone returned variable merges the keys the walk reads', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9]);

    expect(returnedVariableMembers(ReturnedMergeClosureVariableResource::class))->toBe('id: number; x21_a?: number')
        ->and(new ReturnedMergeClosureVariableResource($tag)->resolve(Request::create('/', 'GET', ['key' => 'extra'])))
        ->toBe(['id' => 9, 'x21_a' => 9, 'extra' => 1]);
});

test('a merge closure reads its own local in place of the method\'s local of the same name', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9]);

    expect(returnedVariableMembers(MergeClosureShadowedValueResource::class))->toBe('id: number; outer: string; label: number')
        ->and(new MergeClosureShadowedValueResource($tag)->resolve(Request::create('/')))
        ->toBe(['id' => 9, 'outer' => 'x', 'label' => 9]);
});

test('a merge closure narrows its own local by an instanceof guard in its body', function () {
    $post = new Post;
    $post->forceFill(['id' => 7]);
    $user = new User;
    $user->forceFill(['id' => 3]);
    $sent = function (Post|User $owner): array {
        $image = new Image;
        $image->forceFill(['id' => 21]);
        $image->setRelation('imageable', $owner);

        return new MergeClosureGuardResource($image)->resolve(Request::create('/'));
    };

    expect(publishedResourceKeyTypes(MergeClosureGuardResource::class, Image::class))
        ->toBe(['id' => 'number', 'owner_key?' => 'number'])
        ->and($sent($post))->toBe(['id' => 21, 'owner_key' => 7])
        ->and($sent($user))->toBe(['id' => 21]);
});

test('a value-position local built by key writes or += publishes the keys written before the read', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php']);

    expect(returnedVariableMembers(KeyBuiltLocalValueResource::class))->toBe(
        'plus: { a: number; b: string }; meta: { id: number; name: string; late: boolean }; '
        .'early: { meta: { id: number; name: string } }',
    )->and(new KeyBuiltLocalValueResource($tag)->resolve(Request::create('/')))->toBe([
        'plus' => ['a' => 1, 'b' => 'x'],
        'meta' => ['id' => 9, 'name' => 'php', 'late' => true],
        'early' => ['meta' => ['id' => 9, 'name' => 'php']],
    ]);
});

// A read inside a block that writes the local first may see a write the statements before the block do not hold.
test('a key-built local reads the writes its own body makes before the read, and none from inside a block writing it', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9]);
    $sent = fn (string $x): array => new KeyBuiltLocalScopesResource($tag)->resolve(Request::create('/', 'GET', ['x' => $x]));

    expect(returnedVariableMembers(KeyBuiltLocalScopesResource::class))->toBe(
        'own?: { m: { inner: string; inner2: boolean } }; own_text?: { m: unknown }; early?: { m: { outer: number } }; '
        .'helper: { m: { inner: string; inner2: boolean } }; outer: { outer: number; outer2: number }; nested: unknown',
    )->and($sent('0'))->toBe([
        'own' => ['m' => ['inner' => 'x', 'inner2' => true]],
        'own_text' => ['m' => 'xy'],
        'early' => ['m' => ['outer' => 1]],
        'helper' => ['m' => ['inner' => 'x', 'inner2' => true]],
        'outer' => ['outer' => 1, 'outer2' => 2],
        'nested' => null,
    ])->and($sent('1')['nested'])->toBe(['a' => 1, 'b' => 'x']);
});

// The join binds the read of `$m` to parent::toArray(), which a value read cannot type, so the walk reads that write.
test('a key-built local read where its join holds a value it cannot type reads the whole write before it', function () {
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php', 'slug' => 'php', 'color' => null, 'created_at' => null, 'updated_at' => null]);
    $model = 'id: number; name: string; slug: string; color: string | null; created_at: string | null; '
        .'updated_at: string | null; posts?: Post[]; products?: Product[]';
    $attributes = ['id' => 9, 'name' => 'php', 'slug' => 'php', 'color' => null, 'created_at' => null, 'updated_at' => null];

    expect(returnedVariableMembers(KeyBuiltLocalGapResource::class))->toBe("first: { {$model} }; m: { {$model}; extra: number }")
        ->and(new KeyBuiltLocalGapResource($tag)->resolve(Request::create('/')))
        ->toBe(['first' => $attributes, 'm' => [...$attributes, 'extra' => 1]]);
});
