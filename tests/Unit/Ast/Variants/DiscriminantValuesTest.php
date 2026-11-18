<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\DiscriminantValue;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\DiscriminantValues;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\BackedRevaluedCases;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\Crm\Enums\Status as CrmStatus;

beforeEach(function () {
    PublishedEnums::flush();
});

/**
 * The values one row `k` of the given type and channels sends, each as its PHP value beside its literal, its scalar
 * arms read as standing for enum cases under $scalarsMayBeCases.
 *
 * @param  array<string, array<array-key, mixed>>  $channels
 * @return list<array{mixed, string}>|null
 */
function discriminantValuesOf(string $type, array $channels = [], bool $scalarsMayBeCases = false): ?array
{
    $analysis = new MethodAnalysis(...[
        'properties' => [['name' => 'k', 'type' => $type, 'optional' => false, 'description' => '']],
        ...$channels,
    ]);
    $values = DiscriminantValues::of($analysis, 'k', $scalarsMayBeCases);

    return $values === null
        ? null
        : array_map(static fn (DiscriminantValue $value): array => [$value->value, $value->literal], $values);
}

it('lists each value a finite row sends, in arm order, once each', function (string $type, array $channels, array $values) {
    expect(discriminantValuesOf($type, $channels))->toBe($values);
})->with([
    'a boolean' => ['boolean', [], [[true, 'true'], [false, 'false']]],
    'a nullable boolean' => ['boolean | null', [], [[true, 'true'], [false, 'false'], [null, 'null']]],
    'one boolean literal' => ['false', [], [[false, 'false']]],
    'a boolean repeated by its literal' => ['true | boolean', [], [[true, 'true'], [false, 'false']]],
    'string literals' => ["'a' | 'b'", [], [['a', "'a'"], ['b', "'b'"]]],
    'one string spelled with either quote' => ["'a' | \"a\"", [], [['a', "'a'"]]],
    'number literals and null' => ['0 | 1 | null', [], [[0, '0'], [1, '1'], [null, 'null']]],
    'a negative number and a fraction' => ['-1 | 1.5', [], [[-1, '-1'], [1.5, '1.5']]],
    'a backed enum read directly' => ['StatusType', ['directEnumFqcns' => ['k' => Status::class]], [
        [Status::Draft, '0'],
        [Status::Published, '1'],
    ]],
    'a backed enum\'s names' => ['StatusKind', ['directEnumFqcns' => ['k' => Status::class]], [
        [Status::Draft, "'Draft'"],
        [Status::Published, "'Published'"],
    ]],
    'a nullable pure enum' => ['VisibilityType | null', ['directEnumFqcns' => ['k' => Visibility::class]], [
        [Visibility::Public, "'Public'"],
        [Visibility::Private, "'Private'"],
        [Visibility::Protected, "'Protected'"],
        [Visibility::Internal, "'Internal'"],
        [Visibility::Draft, "'Draft'"],
        [null, 'null'],
    ]],
    'an enum on the inline queue' => ['StatusType', ['inlineEnumFqcns' => ['k' => [Status::class]]], [
        [Status::Draft, '0'],
        [Status::Published, '1'],
    ]],
]);

it('lists nothing for a row with an arm that is not finite', function (string $type, array $channels) {
    expect(discriminantValuesOf($type, $channels))->toBeNull();
})->with([
    'a string' => ['string', []],
    'a number' => ['number', []],
    'a resource' => ['UserResource', []],
    'a literal beside a string' => ["'a' | string", []],
    'a number spelled other than PHP prints it' => ['1.0e2', []],
    'a wrapped enum' => ['AsEnum<typeof Status>', ['enumResources' => ['k' => Status::class]]],
    'an enum the key also wraps' => ['StatusType', [
        'directEnumFqcns' => ['k' => Status::class],
        'enumResources' => ['k' => Status::class],
    ]],
    'an alias no enum on the key spells' => ['StatusType', []],
    'an alias two enums on the key spell' => ['StatusType', [
        'directEnumFqcns' => ['k' => Status::class],
        'inlineEnumFqcns' => ['k' => [CrmStatus::class]],
    ]],
    'a scalar literal of a key reading an enum, as an override may print its cases' => ["'draft' | null", [
        'directEnumFqcns' => ['k' => Status::class],
    ]],
]);

it('keeps null and the booleans of a key reading an enum', function () {
    expect(discriminantValuesOf('StatusType | false | null', ['directEnumFqcns' => ['k' => Status::class]]))->toBe([
        [Status::Draft, '0'],
        [Status::Published, '1'],
        [false, 'false'],
        [null, 'null'],
    ]);
});

it('lists nothing for an alias #[TsCase] makes spell other than what PHP sends', function () {
    $this->mock(EnumsCollector::class)->shouldReceive('collect')->andReturn(collect([BackedRevaluedCases::class]));

    expect(discriminantValuesOf('BackedRevaluedCasesType', ['directEnumFqcns' => ['k' => BackedRevaluedCases::class]]))
        ->toBeNull();
});

it('lists nothing for a key the analysis does not hold', function () {
    expect(DiscriminantValues::of(new MethodAnalysis, 'k', false))->toBeNull();
});

it('lists only null and the booleans of a row whose scalars may stand for enum cases', function (string $type, ?array $values) {
    expect(discriminantValuesOf($type, scalarsMayBeCases: true))->toBe($values);
})->with([
    'number literals' => ['0 | 1', null],
    'string literals' => ["'a' | 'b'", null],
    'a nullable boolean' => ['boolean | null', [[true, 'true'], [false, 'false'], [null, 'null']]],
]);

it('reads a case\'s value and name under the case\'s literal, and no member of a scalar', function () {
    $draft = new DiscriminantValue(Status::Draft, '0');
    $public = new DiscriminantValue(Visibility::Public, "'Public'");
    $pair = static fn (?DiscriminantValue $value): ?array => $value === null ? null : [$value->value, $value->literal];

    expect($pair($draft->member('value')))->toBe([0, '0'])
        ->and($pair($draft->member('name')))->toBe(['Draft', '0'])
        ->and($pair($public->member('name')))->toBe(['Public', "'Public'"])
        ->and($public->member('value'))->toBeNull()
        ->and($draft->member('label'))->toBeNull()
        ->and(new DiscriminantValue(true, 'true')->member('name'))->toBeNull();
});
