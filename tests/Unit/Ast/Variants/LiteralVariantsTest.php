<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\DiscriminantValue;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\LiteralVariants;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\Variant;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\VariantGroup;
use Workbench\App\Http\Resources\Discriminated\DiscriminantFormsResource;

/**
 * A literal writing each key once by its own item, typed as $types gives it, with the given groups read beside it.
 *
 * @param  array<string, string>  $types  each key's type, in item order
 * @param  list<VariantGroup>  $groups
 */
function literalWithGroups(array $types, array $groups): MethodAnalysis
{
    $writes = new LiteralWrites;
    $index = 0;

    foreach ($types as $name => $type) {
        $writes->recordKey($name, ['type' => $type, 'optional' => false], $index++);
    }

    $analysis = new MethodAnalysis;
    $writes->applyTo($analysis, true);
    $analysis->variants = new LiteralVariants(
        $groups,
        $writes,
        true,
        VariantGroup::fingerprint($analysis, array_column($analysis->writtenProperties(), 'name')),
    );

    return $analysis;
}

/**
 * A group whose discriminant sends each of $values, its one dependent typed `'<discriminant><value>'` for each.
 *
 * @param  list<int>  $values
 */
function groupOfValues(string $discriminant, string $dependent, array $values): VariantGroup
{
    return new VariantGroup($discriminant, [$discriminant, $dependent], [], array_map(
        static fn (int $value): Variant => new Variant(
            [new DiscriminantValue($value, (string) $value)],
            [$dependent => ['type' => "'{$discriminant}{$value}'", 'optional' => false]],
        ),
        $values,
    ));
}

/**
 * Each object an analysis's variants print, as `name` (`?` where it may not be sent) => its type.
 *
 * @return list<array<string, string>>
 */
function objectRows(MethodAnalysis $analysis): array
{
    return array_map(static function (MethodAnalysis $object): array {
        $rows = [];

        foreach ($object->properties as $property) {
            $rows[$property['name'].($property['optional'] ? '?' : '')] = $property['type'];
        }

        return $rows;
    }, $analysis->variants?->objects() ?? []);
}

it('publishes one object per value of a sibling key in a nested literal, and each control as one object', function () {
    expect(resourceProps(DiscriminantFormsResource::class))->toMatchArray([
        'negated' => '{ pinned: true; v: number } | { pinned: false; v: string | null }',
        'strict_true' => '{ pinned: true; v: string } | { pinned: false; v: null }',
        'strict_false' => '{ pinned: true; v: number } | { pinned: false; v: string | null }',
        'case_compare' => '{ status: 0; v: null } | { status: 1; v: string | null }',
        'case_not_equal' => '{ status: 0; v: number } | { status: 1; v: string }',
        'value_read' => '{ status: 0; v: string | null } | { status: 1; v: number }',
        'unit_enum_default' => "{ visibility: 'Public'; v: string } | { visibility: 'Private' | 'Protected' | 'Internal' | 'Draft' | null; v: null }",
        'match_true' => '{ status: 0; v: string | null } | { status: 1; v: number }',
        'when_dependent' => '{ pinned: true; until: string | null } | { pinned: false; until?: never }',
        'when_closure' => '{ pinned: true; until: number } | { pinned: false; until?: never }',
        'unless_default' => '{ pinned: true; v: number } | { pinned: false; v: string | null }',
        'two_dependents' => '{ a: string; pinned: true; b: null } | { a: null; pinned: false; b: string | null }',
        'nested_ternary' => '{ pinned: true; v: string } | { pinned: false; v: string | number | null }',
        'two_discriminants' => '{ pinned: true; status: 0; a: string; b: string | null } | { pinned: true; status: 1; a: string; b: number } | { pinned: false; status: 0; a: null; b: string | null } | { pinned: false; status: 1; a: null; b: number }',
        'throw_arm' => '{ priority: 0; v: string } | { priority: 2; v: number }',
        'shared_keys' => '{ id: number; pinned: true; v: string; title: string } | { id: number; pinned: false; v: number; title: string }',
        'cast_discriminant_kept' => '{ pinned: boolean; v: string | null }',
        'method_condition_kept' => '{ featured: boolean; v: string | null }',
        'unpublished_condition_kept' => '{ id: number; v: string | null }',
        'string_discriminant_kept' => '{ title: string; v: number | null }',
        'wrapped_discriminant_kept' => '{ status: AsEnum<typeof Status>; v: number | null }',
        'optional_discriminant_kept' => '{ pinned?: boolean; v: string | null }',
        'undecidable_kept' => '{ pinned: boolean; v: string | null }',
        'same_arms_kept' => '{ pinned: boolean; v: number }',
        'untypable_arm_kept' => '{ pinned: boolean; v: null }',
    ]);
});

it('replays the literal once per variant, a second group\'s variants inside each of the first\'s', function () {
    $analysis = literalWithGroups(
        ['k' => '0 | 1', 'x' => 'string', 'shared' => 'number', 'j' => '0 | 1', 'y' => 'string'],
        [groupOfValues('k', 'x', [0, 1]), groupOfValues('j', 'y', [0, 1])],
    );

    expect(objectRows($analysis))->toBe([
        ['k' => '0', 'x' => "'k0'", 'shared' => 'number', 'j' => '0', 'y' => "'j0'"],
        ['k' => '0', 'x' => "'k0'", 'shared' => 'number', 'j' => '1', 'y' => "'j1'"],
        ['k' => '1', 'x' => "'k1'", 'shared' => 'number', 'j' => '0', 'y' => "'j0'"],
        ['k' => '1', 'x' => "'k1'", 'shared' => 'number', 'j' => '1', 'y' => "'j1'"],
    ]);
});

it('prints a dependent its arm removes as a key that may not be sent, holding nothing', function () {
    $group = new VariantGroup('k', ['k', 'x'], [], [
        new Variant([new DiscriminantValue(true, 'true')], ['x' => ['type' => 'string', 'optional' => false]]),
        new Variant([new DiscriminantValue(false, 'false')], ['x' => null]),
    ]);

    expect(objectRows(literalWithGroups(['k' => 'boolean', 'x' => 'string'], [$group])))->toBe([
        ['k' => 'true', 'x' => 'string'],
        ['k' => 'false', 'x?' => 'never'],
    ]);
});

it('leaves a group flat that would pass 16 objects, and prints none where every group would', function () {
    $alone = literalWithGroups(['k' => 'number', 'x' => 'string'], [groupOfValues('k', 'x', range(0, 16))]);

    expect($alone->variants?->objects())->toBe([]);
});

it('holds for the rows the fold wrote, and for no row written or retyped after it', function () {
    $analysis = literalWithGroups(['k' => '0 | 1', 'x' => 'string'], [groupOfValues('k', 'x', [0, 1])]);
    $variants = $analysis->variants;
    assert($variants !== null);
    $retyped = clone $analysis;
    $retyped->properties[1]['type'] = 'string | null';
    $added = clone $analysis;
    $added->addProperty('more', ['type' => 'number', 'optional' => false]);

    expect($variants->holdsFor($analysis))->toBeTrue()
        ->and($variants->holdsFor($retyped))->toBeFalse()
        ->and($variants->holdsFor($added))->toBeFalse();
});

it('replays the objects once, whichever print asks', function () {
    $variants = literalWithGroups(['k' => '0 | 1', 'x' => 'string'], [groupOfValues('k', 'x', [0, 1])])->variants;
    assert($variants !== null);

    expect($variants->objects())->toBe($variants->objects());
});
