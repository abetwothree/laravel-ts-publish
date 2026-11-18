<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArm;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArms;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\DiscriminantValue;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\LiteralVariants;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\Variant;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\VariantReader;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Variants\Fixtures\VariantFormsResource;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\Return_;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Each group an analysis holds that still holds for it, as its discriminant => one `[literal, [key => type]]` per
 * variant, where an optional key reads `key?` and a removed one `-`.
 *
 * @return array<string, list<array{string, array<string, string>}>>
 */
function variantRows(MethodAnalysis $analysis): array
{
    $rows = [];

    foreach ($analysis->variants->groups ?? [] as $group) {
        if (! $group->holdsFor($analysis)) {
            continue;
        }

        $rows[$group->discriminant] = array_map(static function (Variant $variant): array {
            $dependents = [];

            foreach ($variant->dependents as $key => $result) {
                $dependents[$key.(($result['optional'] ?? false) ? '?' : '')] = $result['type'] ?? '-';
            }

            return [$variant->literal(), $dependents];
        }, $group->variants);
    }

    return $rows;
}

/**
 * The analysis of the fixture method named for a DiscriminantFormsResource cell.
 */
function variantFormsAnalysis(string $cell): MethodAnalysis
{
    return resolve(AstEngine::class)->analyzeMethod(VariantFormsResource::class, Str::camel($cell));
}

/**
 * The variants the fixture method named for a cell holds, as variantRows() lists them.
 *
 * @return array<string, list<array{string, array<string, string>}>>
 */
function variantsOf(string $cell): array
{
    return variantRows(variantFormsAnalysis($cell));
}

/**
 * The reader's read of a fixture method's returned literal, each key written once by its own item with the type $types
 * gives it, and every arm handed over by $armsOf.
 *
 * @param  array<string, string>  $types  each key's type, in item order
 * @param  Closure(Expr): ?ConditionalArms  $armsOf
 */
function readFixtureLiteral(string $method, array $types, Closure $armsOf): ?LiteralVariants
{
    $return = resolve(MethodLocator::class)->locate(VariantFormsResource::class, $method)?->method->stmts[0] ?? null;
    assert($return instanceof Return_ && $return->expr instanceof Array_);
    $writes = new LiteralWrites;
    $keyNames = [];

    foreach (array_keys($types) as $index => $name) {
        $writes->recordKey($name, ['type' => $types[$name], 'optional' => false], $index);
        $keyNames[$index] = $name;
    }

    $analysis = new MethodAnalysis;
    $writes->applyTo($analysis, true);

    return VariantReader::read(
        $return->expr,
        $keyNames,
        $writes,
        true,
        $analysis,
        new AnalysisScope(new ReflectionClass(VariantFormsResource::class), Post::class),
        $armsOf,
        static fn (array $result): array => $result,
    );
}

/**
 * Arms for the strict_true literal's `v`: its true arm sends $true, its false arm `null`.
 *
 * @param  array{type: string, optional: bool}  $true
 * @return Closure(Expr): ?ConditionalArms
 */
function strictTrueArms(array $true, ?Closure $whileRead = null): Closure
{
    return static function (Expr $expr) use ($true, $whileRead): ?ConditionalArms {
        if (! $expr instanceof Ternary) {
            return null;
        }

        if ($whileRead !== null) {
            $whileRead($expr);
        }

        return new ConditionalArms([
            new ConditionalArm($expr->cond, $expr->if, $true),
            new ConditionalArm(null, $expr->else, ['type' => 'null', 'optional' => false]),
        ]);
    };
}

it('reads which arm each key takes for every value a sibling key sends', function () {
    expect(variantsOf('negated'))->toBe(['pinned' => [['true', ['v' => 'number']], ['false', ['v' => 'string | null']]]])
        ->and(variantsOf('unit_enum_default'))->toBe(['visibility' => [
            ["'Public'", ['v' => 'string']],
            ["'Private' | 'Protected' | 'Internal' | 'Draft' | null", ['v' => 'null']],
        ]])
        ->and(variantsOf('when_dependent'))->toBe(['pinned' => [['true', ['until' => 'string | null']], ['false', ['until' => '-']]]])
        ->and(variantsOf('throw_arm'))->toBe(['priority' => [['0', ['v' => 'string']], ['2', ['v' => 'number']]]])
        ->and(variantsOf('two_discriminants'))->toBe([
            'pinned' => [['true', ['a' => 'string']], ['false', ['a' => 'null']]],
            'status' => [['0', ['b' => 'string | null']], ['1', ['b' => 'number']]],
        ])
        ->and(variantsOf('nullsafe_value'))->toBe(['p' => [['0', ['v' => 'string']], ['1 | 2 | 3', ['v' => 'number']], ['null', ['v' => 'null']]]]);
});

it('reads one variant per arm for each other condition form', function (string $cell, array $variants) {
    expect(variantsOf($cell))->toBe($variants);
})->with([
    'strict_true' => ['strict_true', ['pinned' => [['true', ['v' => 'string']], ['false', ['v' => 'null']]]]],
    'strict_false' => ['strict_false', ['pinned' => [['true', ['v' => 'number']], ['false', ['v' => 'string | null']]]]],
    'case_compare' => ['case_compare', ['status' => [['0', ['v' => 'null']], ['1', ['v' => 'string | null']]]]],
    'case_not_equal' => ['case_not_equal', ['status' => [['0', ['v' => 'number']], ['1', ['v' => 'string']]]]],
    'value_read' => ['value_read', ['status' => [['0', ['v' => 'string | null']], ['1', ['v' => 'number']]]]],
    'match_true' => ['match_true', ['status' => [['0', ['v' => 'string | null']], ['1', ['v' => 'number']]]]],
    'when_closure' => ['when_closure', ['pinned' => [['true', ['until' => 'number']], ['false', ['until' => '-']]]]],
    'unless_default' => ['unless_default', ['pinned' => [['true', ['v' => 'number']], ['false', ['v' => 'string | null']]]]],
    'two_dependents' => ['two_dependents', ['pinned' => [
        ['true', ['a' => 'string', 'b' => 'null']],
        ['false', ['a' => 'null', 'b' => 'string | null']],
    ]]],
    'nested_ternary' => ['nested_ternary', ['pinned' => [['true', ['v' => 'string']], ['false', ['v' => 'string | number | null']]]]],
    'shared_keys' => ['shared_keys', ['pinned' => [['true', ['v' => 'string']], ['false', ['v' => 'number']]]]],
    'a read assigned before the group' => ['mutate_before', ['pinned' => [['true', ['v' => 'string']], ['false', ['v' => 'null']]]]],
    'arms that spell one name for two enums' => ['same_name_arms', ['pinned' => [
        ['true', ['v' => 'StatusType']],
        ['false', ['v' => 'StatusType']],
    ]]],
]);

// The first group spans the assignment, so it declines and leaves `v` to the second, which PHP reads after it.
it('leaves a declined group\'s keys to a later discriminant', function () {
    expect(variantsOf('owned_after_decline'))->toBe(['b' => [['true', ['v' => 'string']], ['false', ['v' => 'null']]]]);
});

// Eight levels deep the true arm keeps its own union, the ninth ternary's, rather than select its `title` arm.
it('selects a value through eight levels of arms at most', function () {
    expect(variantsOf('nine_levels'))->toBe(['pinned' => [['true', ['v' => 'string | number']], ['false', ['v' => 'null']]]]);
});

it('makes no group for a control, or for a literal no variant can stand for', function (string $cell) {
    expect(variantsOf($cell))->toBe([]);
})->with([
    'cast_discriminant_kept',
    'method_condition_kept',
    'unpublished_condition_kept',
    'string_discriminant_kept',
    'wrapped_discriminant_kept',
    'optional_discriminant_kept',
    'undecidable_kept',
    'same_arms_kept',
    'untypable_arm_kept',
    'dropped_arm_kept',
    'scalar_local_kept',
    'merge_parent_kept',
    'mutate_between_kept',
    'index_signature',
    'spread_writes',
    'two_returns',
]);

it('records each group\'s keys in item order, with the row each had at the fold', function () {
    $analysis = variantFormsAnalysis('two_dependents');
    $group = $analysis->variants->groups[0] ?? null;

    expect($group?->discriminant)->toBe('pinned')
        ->and($group?->keys)->toBe(['a', 'pinned', 'b'])
        ->and($group?->fingerprints)->toBe([
            'a' => ['type' => 'string | null', 'optional' => false],
            'pinned' => ['type' => 'boolean', 'optional' => false],
            'b' => ['type' => 'string | null', 'optional' => false],
        ])
        ->and($group?->holdsFor($analysis))->toBeTrue();
});

it('stops holding a group once a row it was read from changes', function () {
    $analysis = variantFormsAnalysis('shared_keys');
    $retyped = clone $analysis;
    $retyped->properties[2]['type'] = 'string';
    $shared = clone $analysis;
    $shared->properties[0]['type'] = 'string';
    $group = $analysis->variants->groups[0] ?? null;

    expect($group?->holdsFor($retyped))->toBeFalse()
        ->and($group?->holdsFor($shared))->toBeTrue();
});

it('reads a group under a `@return` whose fill changes a row, which then no longer holds', function () {
    $analysis = variantFormsAnalysis('docblock_filled');

    expect($analysis->variants?->groups)->toHaveCount(1)
        ->and(variantRows($analysis))->toBe([]);
});

// The declared `string` stands beside the `null` the body proves, so both rows stay as the fold wrote them.
it('keeps a group under a `@return` whose fill leaves its rows as they were', function () {
    expect(variantsOf('docblock_agreeing'))->toBe(['pinned' => [['true', ['v' => 'string']], ['false', ['v' => 'null']]]]);
});

// PHP writes the keys after the spread over any it sends, though the item loop reads that spread's keys as unnamed.
it('reads a nested literal\'s group past a spread of a resource before its keys', function () {
    $return = resolve(MethodLocator::class)->locate(VariantFormsResource::class, 'spreadArmLeads')?->method->stmts[0] ?? null;
    assert($return instanceof Return_ && $return->expr instanceof Array_);
    $nested = new ResourceAstAnalyzer(new ReflectionClass(VariantFormsResource::class), Post::class)->returnArrayAnalysis($return->expr);

    expect(variantRows($nested))->toBe(['pinned' => [['true', ['v' => 'string']], ['false', ['v' => 'null']]]]);
});

// The body fallback prints the case as `0 | 1`, but PHP holds the case, which is always truthy.
it('makes no group from a scalar a body fallback prints where PHP holds a case', function () {
    $published = resolve(MethodReturnTypeResolver::class)->resolve(VariantFormsResource::class, 'casesAsLiterals');
    $analysis = resolve(AstEngine::class)->analyzeMethod(VariantFormsResource::class, 'casesAsLiterals', casesAsLiterals: true, readsCall: true);

    expect($published['type'] ?? null)->toStartWith('{ s: 0 | 1;')
        ->and(variantRows($analysis))->toBe([]);
});

it('prints a variant\'s discriminant as its values\' literals, both booleans as `boolean`', function (array $values, string $literal) {
    expect(new Variant($values, [])->literal())->toBe($literal);
})->with([
    'one boolean' => [[new DiscriminantValue(true, 'true')], 'true'],
    'both booleans' => [[new DiscriminantValue(true, 'true'), new DiscriminantValue(false, 'false')], 'boolean'],
    'both booleans and null' => [
        [new DiscriminantValue(true, 'true'), new DiscriminantValue(false, 'false'), new DiscriminantValue(null, 'null')],
        'boolean | null',
    ],
    'a boolean and null' => [[new DiscriminantValue(false, 'false'), new DiscriminantValue(null, 'null')], 'false | null'],
    'cases' => [[new DiscriminantValue(Status::Draft, '0'), new DiscriminantValue(Status::Published, '1')], '0 | 1'],
    'strings' => [[new DiscriminantValue('image', "'image'"), new DiscriminantValue('video', "'video'")], "'image' | 'video'"],
]);

it('reads no arm for a literal with one keyed item, or none whose value picks between arms', function (string $method, array $types) {
    expect(readFixtureLiteral($method, $types, static fn (): never => throw new LogicException('No arm is read.')))->toBeNull();
})->with([
    'one keyed item' => ['oneKey', ['v' => 'string | null']],
    'no value picking between arms' => ['plainKeys', ['pinned' => 'boolean', 'title' => 'string']],
]);

it('makes no group where an arm is untyped or holds a gap', function (array $true) {
    $types = ['pinned' => 'boolean', 'v' => 'string | null'];

    expect(readFixtureLiteral('strictTrue', $types, strictTrueArms($true)))->toBeNull()
        ->and(readFixtureLiteral('strictTrue', $types, strictTrueArms(['type' => 'string', 'optional' => false]))?->groups)
        ->toHaveCount(1);
})->with([
    'untyped' => [['type' => 'unknown', 'optional' => false]],
    'a gap' => [['type' => VisibleGaps::withArm('string', 1), 'optional' => false]],
]);

it('makes no group where reading its arms leaves one out, and keeps neither the drop nor its count', function () {
    $scope = new AnalysisScope(new ReflectionClass(VariantFormsResource::class), Post::class);
    $before = DroppedUnionArms::dropped();
    $read = readFixtureLiteral('strictTrue', ['pinned' => 'boolean', 'v' => 'string | null'], strictTrueArms(
        ['type' => 'string', 'optional' => false],
        static fn (Expr $expr) => DroppedUnionArms::record($expr, $scope, 'ternary-narrowed'),
    ));

    expect($read)->toBeNull()
        ->and(DroppedUnionArms::dropped())->toBe($before);
});
