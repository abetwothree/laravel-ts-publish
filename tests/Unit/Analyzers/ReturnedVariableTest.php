<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedGuardedVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedHelperVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedMergeVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedOnlyVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedOpaqueVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedReplacedVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedSelfSpreadGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedSelfSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedUnionAssignResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedUnmodeledOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedUnmodeledParentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ReturnedVariableLinesService;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithReturnedVariable;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use PhpParser\Node;
use Workbench\App\Events\ManifestAssembled;
use Workbench\App\Http\Resources\ReturnedParentVariableResource;
use Workbench\App\Http\Resources\ReturnedVariableBranchesResource;
use Workbench\App\Http\Resources\ReturnedVariableResource;
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

test('a variable the walk cannot read is skipped, so the literal branch keeps its keys required', function () {
    expect(returnedVariableMembers(ReturnedOpaqueVariableResource::class))->toBe('id: number; name: string');
});

test('a base read as nothing never turns a literal\'s keys optional', function (string $class) {
    expect(returnedVariableMembers($class))->toBe('id: number');
})->with([
    'parent::toArray() with no model' => [ReturnedUnmodeledParentResource::class],
    'only() with no model' => [ReturnedUnmodeledOnlyResource::class],
]);

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
