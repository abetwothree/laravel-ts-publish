<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedBodylessParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedBranchReplacedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedCastLoneVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedCastPartialHelperResource;
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
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedEnumKeyResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedEnumSpreadResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedFilterThenAppendedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedFilterThenOpaqueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedGuardedVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedGuardOnlyVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedHelperVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedInterpolatedUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedLoopResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedMergeVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedNestedKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedNestedUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedNumericKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOnlyVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOpaqueBesideReadableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOpaqueHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOpaqueParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOpaqueVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedOptionalResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedParentWithResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedPartialHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedPartialValueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedPartlyReadSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedPushedKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedReadableHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedReadableHelperVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedRejectedHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedReplacedVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedRequiredResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSelfSpreadGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSelfSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedShapedLoneVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSkippingParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedSkippingParentResource;
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
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\ReturnedVariableLinesService;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithReturnedVariable;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Http\Request;
use PhpParser\Node;
use Workbench\App\Enums\Status;
use Workbench\App\Events\ManifestAssembled;
use Workbench\App\Http\Resources\CategoryBranchesResource;
use Workbench\App\Http\Resources\ReturnedParentVariableResource;
use Workbench\App\Http\Resources\ReturnedVariableBranchesResource;
use Workbench\App\Http\Resources\ReturnedVariableResource;
use Workbench\App\Models\Category;
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
         * Whether the branch gate reads `$d` in a body.
         *
         * @param  array<Node>  $stmts
         */
        public function readsD(array $stmts): bool
        {
            return $this->readsVariableArray($stmts, 'd');
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
    $walk = walkedVariable($body);

    expect(implode('; ', array_map(fn (array $p): string => $p['name'].($p['optional'] ? '?' : '').': '.$p['type'], $walk->properties)))
        ->toBe($members);
})->with([
    'beside a value held before' => ['$d = ["a" => 1]; if ($x) { $d["a"] = $nowhere; }', 'a: number'],
    'beside a lone null' => ['$d = []; if ($x) { $d["b"] = null; } else { $d["b"] = $nowhere; }', 'b: null'],
    'in every branch' => ['$d = []; if ($x) { $d["c"] = $nowhere; } else { $d["c"] = $nowhere; }', 'c: unknown'],
]);

test('a key no branch rewrites keeps one copy of its queued classes however many joins it passes', function () {
    $chains = implode(' ', array_map(
        fn (int $i): string => "if (\$x{$i}) { \$d['k{$i}'] = {$i}; } else { \$d['k{$i}'] = 'v'; }",
        range(1, 4),
    ));
    $walk = walkedVariable('$d = ["meta" => ["status" => $this->status, "author" => $this->author]]; '
        .$chains.' foreach ($rows as $row) { $d["n"] = 1; }');

    expect($walk->inlineEnumFqcns['meta'] ?? null)->toBe([Status::class])
        ->and($walk->inlineModelFqcns['meta'] ?? null)->toBe([User::class]);
});

test('a whole write that always runs in a branch replaces an unread write before it', function (string $body, string $keys, bool $complete) {
    $walk = walkedVariable($body);

    expect(implode(', ', array_map(fn (array $p): string => $p['name'].($p['optional'] ? '?' : ''), $walk->properties)))->toBe($keys)
        ->and($walk->readsCompletely)->toBe($complete);
})->with([
    'every branch replaces it' => ['$d = ["a" => 1]; $d[$k] = 2; if ($x) { $d = ["b" => 1]; } else { $d = ["c" => 1]; }', 'b?, c?', true],
    'one branch keeps it' => ['$d = ["a" => 1]; $d[$k] = 2; if ($x) { $d = ["b" => 1]; }', 'a?, b?', false],
    'a write that may not run merges into it' => ['$d = ["a" => 1]; $d[$k] = 2; try { $d = ["b" => 1]; } catch (Throwable) {}', 'a, b?', false],
]);
