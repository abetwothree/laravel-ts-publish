<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResponseExtras;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ObjectMembers;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\DiscriminantValue;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\LiteralVariants;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\Variant;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\VariantGroup;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Variants\Fixtures\VariantFormsResource;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Return_;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;

it('reads every member a fill typed class-less, keeping a value written in place', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('override', ['type' => 'User', 'optional' => false, 'customImports' => ['@js/types/ui' => ['User']]]);
    $analysis->addProperty('in_place', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->addProperty('filled', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->declaredTokenFqcns['filled'] = [User::class];

    InlineObjectResult::readFillsClassLess($analysis);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['override' => 'User', 'in_place' => 'User', 'filled' => 'unknown'])
        ->and($analysis->channelFqcns('in_place'))->toBe([User::class])
        ->and($analysis->hasFqcnChannel('filled'))->toBeFalse()
        ->and($analysis->declaredTokenFqcns)->toBe([])
        ->and($analysis->customImports)->toBe(['@js/types/ui' => ['User']]);
});

it('reads a fill class-less whatever its siblings', function () {
    $analysis = new MethodAnalysis;
    $analysis->addProperty('filled', ['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
    $analysis->declaredTokenFqcns['filled'] = [User::class];

    InlineObjectResult::readFillsClassLess($analysis);

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['filled' => 'unknown'])
        ->and($analysis->hasFqcnChannel('filled'))->toBeFalse();
});

it('queues the enums a member wraps on the object\'s enum queue, as their tokens read bare', function (bool $tolki) {
    config()->set('ts-publish.enums.use_tolki_package', $tolki);
    $analysis = new MethodAnalysis;
    $analysis->addProperty('first', ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => CrmStatus::class]);
    $analysis->addProperty('subject', ['type' => 'StatusType | RoleType', 'optional' => false, 'multiEnumResourceFqcns' => [Status::class, Role::class]]);

    $result = new InlineObjectResult()->of($analysis);

    expect($result['type'])->toBe('{ first: StatusType; subject: StatusType | RoleType }')
        ->and($result['embeddedEnumFqcns'] ?? [])->toBe([CrmStatus::class, Status::class, Role::class])
        ->and($result)->not->toHaveKey('embeddedEnumResourceFqcns');
})->with(['tolki on' => [true], 'tolki off' => [false]]);

it('prints the open marker in the gap mode where the object may hold keys no name names, and none out of it', function (array $properties, array $arms, bool $shows, string $type) {
    $analysis = new MethodAnalysis;

    foreach ($properties as $name => $value) {
        $analysis->addProperty($name, ['type' => $value, 'optional' => false]);
    }

    $analysis->holdUnreadKeys();
    $read = fn (): array => new InlineObjectResult()->of($analysis, $arms);
    $printed = $shows ? resolve(AnalysisMemo::class)->showingGaps($read) : $read();

    expect($printed['type'])->toBe($type);
})->with([
    'a literal, the mode off' => [['a' => 'string'], [], false, '{ a: string }'],
    'a literal, the mode on' => [['a' => 'string'], [], true, '{ a: string; #$open: unknown }'],
    'a member holding a gap' => [['a' => 'string | $gap1'], [], true, '{ a: string | $gap1; #$open: unknown }'],
    'arms only' => [[], [['type' => '{ b: number }', 'result' => ['type' => '{ b: number }', 'optional' => false]]], true, '{ b: number } & { #$open: unknown }'],
    'arms beside keys' => [['a' => 'string'], [['type' => '{ b: number }', 'result' => ['type' => '{ b: number }', 'optional' => false]]], true, "Omit<{ b: number }, 'a'> & { a: string; #\$open: unknown }"],
]);

it('reads a literal holding no named key as a Record, with no marker', function () {
    $analysis = new MethodAnalysis;
    $analysis->holdUnreadKeys();

    expect(resolve(AnalysisMemo::class)->showingGaps(fn (): array => new InlineObjectResult()->of($analysis))['type'])
        ->toBe('Record<string, unknown>');
});

it('prints no open marker beside a string-keyed Record arm, which already types every key', function () {
    $analysis = new MethodAnalysis;
    $analysis->holdsUnnamedKeys = true;
    $map = ['type' => 'Record<string, number>', 'result' => ['type' => 'Record<string, number>', 'optional' => false]];

    expect(resolve(AnalysisMemo::class)->showingGaps(fn (): array => new InlineObjectResult()->of($analysis, [$map]))['type'])
        ->toBe('Record<string, number>');
});

/**
 * The literal a VariantFormsResource method returns, read as a nested literal is.
 */
function nestedFormLiteral(string $method): MethodAnalysis
{
    $return = resolve(MethodLocator::class)->locate(VariantFormsResource::class, $method)?->method->stmts[0] ?? null;
    assert($return instanceof Return_ && $return->expr instanceof Array_);

    return new ResourceAstAnalyzer(new ReflectionClass(VariantFormsResource::class), Post::class)->returnArrayAnalysis($return->expr);
}

/**
 * The type a VariantFormsResource method's literal prints as a nested literal, beside the arms its spreads keep.
 */
function printedFormLiteral(string $method): string
{
    $analysis = nestedFormLiteral($method);

    return new InlineObjectResult()->of($analysis, $analysis->spreadArms())['type'];
}

it('prints the first two groups\' 16 objects, a third group that would pass 16 left flat in each', function () {
    $writes = new LiteralWrites;
    $types = ['a' => 'number', 'x' => 'string', 'b' => 'number', 'y' => 'string', 'c' => 'number', 'z' => 'string'];

    foreach (array_keys($types) as $index => $name) {
        $writes->recordKey($name, ['type' => $types[$name], 'optional' => false], $index);
    }

    $analysis = new MethodAnalysis;
    $writes->applyTo($analysis, true);
    $groups = array_map(static fn (array $pair): VariantGroup => new VariantGroup($pair[0], $pair, [], array_map(
        static fn (int $value): Variant => new Variant(
            [new DiscriminantValue($value, (string) $value)],
            [$pair[1] => ['type' => "'{$pair[0]}{$value}'", 'optional' => false]],
        ),
        range(0, 3),
    )), [['a', 'x'], ['b', 'y'], ['c', 'z']]);
    $analysis->variants = new LiteralVariants($groups, $writes, true, VariantGroup::fingerprint($analysis, array_keys($types)), []);
    $objects = [];

    foreach (range(0, 3) as $a) {
        foreach (range(0, 3) as $b) {
            $objects[] = "{ a: {$a}; x: 'a{$a}'; b: {$b}; y: 'b{$b}'; c: number; z: string }";
        }
    }

    expect(new InlineObjectResult()->of($analysis)['type'])->toBe(implode(' | ', $objects));
});

it('prints a literal flat once a row the fold wrote changes', function () {
    $analysis = nestedFormLiteral('sharedKeys');
    $analysis->properties[0]['type'] = 'string';

    expect(new InlineObjectResult()->of($analysis)['type'])->toBe('{ id: string; pinned: boolean; v: string | number; title: string }');
});

it('prints a method\'s literal flat where its `@return` changes a row, and split where its rows are the body\'s', function () {
    $resolver = resolve(MethodReturnTypeResolver::class);

    expect($resolver->resolve(VariantFormsResource::class, 'docblockFilled')['type'] ?? null)->toBe('{ pinned: boolean; v?: string }')
        ->and($resolver->resolve(VariantFormsResource::class, 'docblockAgreeing')['type'] ?? null)->toBe('{ pinned: boolean; v: string }')
        ->and($resolver->resolve(VariantFormsResource::class, 'strictTrue')['type'] ?? null)
        ->toBe('{ pinned: true; v: string } | { pinned: false; v: null }');
});

// The body's own rows are what an outer `@return` keeps for a nested literal, so the variants still hold for them.
it('prints a nested literal split under an outer `@return` that leaves its rows as the body reads them', function () {
    expect(resolve(AstEngine::class)->analyzeMethod(VariantFormsResource::class, 'nestedUnderReturn')->properties[0]['type'])
        ->toBe('{ pinned: true; v: string } | { pinned: false; v: null }');
});

it('carries the one object a split literal prints, with its own classes, for a reader that takes its keys back', function () {
    $analysis = nestedFormLiteral('caseCompare');
    $split = new InlineObjectResult()->of($analysis);
    $one = ValueResult::unsplit($split);

    expect($split['type'])->toBe('{ status: 0; v: null } | { status: 1; v: string | null }')
        ->and($split)->not->toHaveKey('embeddedEnumFqcns')
        ->and($one['type'])->toBe('{ status: StatusType; v: string | null }')
        ->and($one['embeddedEnumFqcns'] ?? [])->toBe([Status::class])
        ->and(ObjectMembers::members($split))->toBe([
            ['status', ['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class]],
            ['v', ['type' => 'string | null', 'optional' => false]],
        ]);
});

it('reads a later resource arm\'s keys from its one object, whatever variants its own literal holds', function () {
    expect(printedFormLiteral('keyBeforeGroupedArm'))
        ->toBe("Omit<PostPriorityResource, 'escalated_at'> & { escalated_at: number | string | null }");
});

it('leads each object with the spread arm that leads the literal', function () {
    $analysis = nestedFormLiteral('spreadArmLeads');
    $result = new InlineObjectResult()->of($analysis, $analysis->spreadArms());

    expect($result['type'])->toBe(
        "Omit<UserResource, 'pinned' | 'v'> & { pinned: true; v: string } | Omit<UserResource, 'pinned' | 'v'> & { pinned: false; v: null }",
    )->and($result['embeddedResourceFqcns'] ?? [])->toBe([UserResource::class]);
});

it('prints a value Laravel may remove itself as optional in its arm, and a removed arm as never', function () {
    expect(printedFormLiteral('whenLoadedAlone'))->toBe('{ author?: User }')
        ->and(printedFormLiteral('whenLoadedArm'))->toBe('{ pinned: true; author?: User } | { pinned: false; author?: never }');
});

it('prints the [] a with() key sends in its removed arm, beside the keys at with()\'s own level, flat', function () {
    expect(ResponseExtras::withOf(VariantFormsResource::class)->object['type'] ?? null)->toBe(
        '{ pinned: boolean; v: string | null; meta: { pinned: true; until: string | null } | { pinned: false; until: never[] } }',
    );
});
