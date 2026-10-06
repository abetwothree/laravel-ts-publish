<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedBodylessParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedCastLoneVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedCastPartialHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedCoalesceKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedDecliningParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedDecliningParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedDynamicKeyAloneResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedDynamicKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedEnumKeyResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedEnumSpreadResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedFilterThenAppendedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedFilterThenOpaqueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedGuardedVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedGuardOnlyVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedHelperVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedMergeVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedNestedKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedOnlyVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedOpaqueBesideReadableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedOpaqueHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedOpaqueParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedOpaqueVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedOptionalResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedPartialHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedPartialValueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedPushedKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedReadableHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedReadableHelperVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedRejectedHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedReplacedVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedRequiredResetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedSelfSpreadGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedSelfSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedShapedLoneVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedSkippingParentChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedSkippingParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedTryLiteralThenOpaqueResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedUnionAssignResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedUnmodeledOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedUnmodeledParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedUnsetKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedVanishingKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedVanishingSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedVariableLinesService;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithReturnedVariable;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use PhpParser\Node;
use Workbench\App\Events\ManifestAssembled;
use Workbench\App\Http\Resources\ReturnedParentVariableResource;
use Workbench\App\Http\Resources\ReturnedVariableBranchesResource;
use Workbench\App\Http\Resources\ReturnedVariableResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
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

test('a key re-set conditionally keeps an optional old value optional', function () {
    expect(returnedVariableMembers(ReturnedOptionalResetResource::class))->toBe('a?: number');
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

test('a base read as nothing never turns a literal\'s keys optional', function (string $class) {
    expect(returnedVariableMembers($class))->toBe('id: number');
})->with([
    'parent::toArray() with no model' => [ReturnedUnmodeledParentResource::class],
    'only() with no model' => [ReturnedUnmodeledOnlyResource::class],
]);

test('a base not read completely never turns a literal\'s keys optional', function (string $class) {
    expect(returnedVariableMembers($class))->toBe('id: number; name: string');
})->with([
    'a helper returning the model\'s toArray()' => [ReturnedOpaqueHelperResource::class],
    'a helper building on the model\'s toArray()' => [ReturnedPartialHelperResource::class],
    'a parent whose own toArray() it cannot read' => [ReturnedOpaqueParentChildResource::class],
    'a helper whose own variable the gate rejects' => [ReturnedRejectedHelperResource::class],
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
    'an unset() key' => [ReturnedUnsetKeyResource::class],
    'a ??= of a key it does not hold' => [ReturnedCoalesceKeyResource::class],
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
    expect(returnedVariableMembers(ReturnedOpaqueBesideReadableResource::class))->toBe('id: number; name: string; y: number');
});

test('a return a parent\'s sweep skips makes a child variable built on it skip, and leaves the parent\'s own shape', function (string $parent, string $parentShape, string $child) {
    expect(returnedVariableMembers($parent))->toBe($parentShape)
        ->and(returnedVariableMembers($child))->toBe('id: number; name: string');
})->with([
    'a return beside its literal' => [
        ReturnedSkippingParentResource::class, 'id: number', ReturnedSkippingParentChildResource::class,
    ],
    'a return the first-return fallback leaves unread' => [
        ReturnedDecliningParentResource::class, '', ReturnedDecliningParentChildResource::class,
    ],
]);

test('a parent delegating to no model makes a child variable built on it skip', function () {
    expect(returnedVariableMembers(ReturnedBodylessParentChildResource::class))->toBe('id: number; name: string');
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

test('the body fallback reads a vague helper that returns a variable, and still declines a mixed one', function (string $class, string $method, string $expected) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve($class, $method)['type'] ?? null)->toBe($expected);
})->with([
    'a static helper' => [ReturnedVariableLinesService::class, 'lines', '{ unit: string; tax: number }'],
    'a container instance' => [QuoteLinesService::class, 'lines', '{ unit: string; tax: number }'],
    'a literal beside a variable' => [ReturnedVariableLinesService::class, 'mixedLines', 'unknown[]'],
]);
