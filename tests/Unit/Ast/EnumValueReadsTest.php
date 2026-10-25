<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\BackedRenamedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\BackedRevaluedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\PureRenamedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\PureRevaluedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\UnsafeIntegerBackedStatus;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\EnumInterfaceReads;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\PostHelperParamsResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\EnumCasePayload;
use Workbench\App\ValueObjects\EnumValuesPayload;

beforeEach(function () {
    PublishedEnums::flush();
});

it('types a backed enum value read as the published alias or the literal union', function () {
    expect(analyzedKeyTypes(EnumValuesPayload::class))->toBe([
        'grade' => 'GradeType | null',
        'fallback_grade' => 'GradeType',
        'grade_name' => 'GradeKind',
        'priority' => 'PriorityType',
        'priority_name' => 'PriorityKind',
        'visibility' => 'VisibilityType',
        'color' => "'red' | 'green' | 'blue' | 'amber' | 'gray' | 'purple'",
        'color_name' => "'Red' | 'Green' | 'Blue' | 'Amber' | 'Gray' | 'Purple'",
        'excluded' => "'foo' | 'bar'",
        'excluded_name' => "'Foo' | 'Bar'",
        'grade_list' => 'GradeType[]',
        'meta' => '{ grade_name: GradeKind }',
    ])->and(analyzedImportNames(EnumValuesPayload::class))
        ->toBe(['GradeKind', 'GradeType', 'PriorityKind', 'PriorityType', 'VisibilityType']);
});

it('sends values the published types admit', function () {
    expect(json_encode(new EnumValuesPayload()->toArray()))->toBe(
        '{"grade":null,"fallback_grade":"pass","grade_name":"Pass","priority":0,"priority_name":"Low",'
        .'"visibility":"Public","color":"amber","color_name":"Amber","excluded":"foo","excluded_name":"Foo",'
        .'"grade_list":["pass"],"meta":{"grade_name":"Pass"}}',
    );
});

it('publishes the literal union when enum publishing is off or excludes the enum', function (array $config) {
    config()->set($config);

    expect(analyzedKeyTypes(EnumValuesPayload::class))->toMatchArray([
        'fallback_grade' => "'pass' | 'fail'",
        'grade_name' => "'Pass' | 'Fail'",
    ])->and(analyzedImportNames(EnumValuesPayload::class))->not->toContain('GradeType');
})->with([
    'publishing off' => [['ts-publish.enums.enabled' => false]],
    'excluded' => [['ts-publish.enums.excluded' => [Grade::class]]],
]);

it('replays no memoized alias once the enums config changes', function () {
    expect(analyzedKeyTypes(EnumValuesPayload::class)['fallback_grade'])->toBe('GradeType');

    config()->set('ts-publish.enums.enabled', false);

    expect(analyzedKeyTypes(EnumValuesPayload::class)['fallback_grade'])->toBe("'pass' | 'fail'");
});

it('publishes the literal union for an enum whose cases #[TsCase] renames or revalues', function () {
    expect(EnumValueTypes::value(Color::class, true)['type'])->toBe("'red' | 'green' | 'blue' | 'amber' | 'gray' | 'purple'")
        ->and(EnumValueTypes::name(Color::class, true)['type'])->toBe("'Red' | 'Green' | 'Blue' | 'Amber' | 'Gray' | 'Purple'")
        ->and(EnumValueTypes::value(Color::class, true))->not->toHaveKey('directEnumFqcn');
});

it('keeps an enum alias only while #[TsCase] leaves the case scalars it is built from', function (
    string $enum,
    string $read,
    string $type,
) {
    $this->mock(EnumsCollector::class)->shouldReceive('collect')->andReturn(collect([$enum]));

    expect(EnumValueTypes::{$read}($enum, true)['type'])->toBe($type);
})->with([
    'a pure name beside a renamed case' => [PureRenamedCases::class, 'name', 'PureRenamedCasesType'],
    'a pure name beside a revalued case' => [PureRevaluedCases::class, 'name', "'Visible' | 'Hidden'"],
    'a backed name beside a renamed case' => [BackedRenamedCases::class, 'name', "'Visible' | 'Hidden'"],
    'a backed name beside a revalued case' => [BackedRevaluedCases::class, 'name', 'BackedRevaluedCasesKind'],
    'a backed value beside a renamed case' => [BackedRenamedCases::class, 'value', 'BackedRenamedCasesType'],
]);

it('reads an enum value in an import-less shape as the literal union', function () {
    expect(publishedResourceKeyTypes(PostHelperParamsResource::class, Post::class)['status_label'])
        ->toBe("{ label: 'Draft' | 'Published'; value: 0 | 1 }");
});

it('publishes number for an enum with a case past the safe integer range', function () {
    expect(EnumValueTypes::value(UnsafeIntegerBackedStatus::class, true)['type'])->toBe('number');
});

it('declines a pure enum value and types the enum interfaces by their scalars', function () {
    expect(EnumValueTypes::value(Visibility::class, true))->toBeNull()
        ->and(analyzedKeyTypes(EnumInterfaceReads::class))->toBe(['backed' => 'string | number', 'unit' => 'string']);
});

it('reads an enum case, a local holding one and a ternary of cases as the enum', function () {
    expect(analyzedKeyTypes(EnumCasePayload::class))->toBe([
        'case_value' => 'GradeType',
        'case_name' => 'PriorityKind',
        'local_value' => 'GradeType',
        'picked' => 'GradeType',
        'parsed' => 'GradeType',
        'maybe' => 'GradeType | null',
        'label' => 'string',
    ]);
});

it('sends the values the enum case reads publish', function () {
    expect(json_encode(new EnumCasePayload()->toArray()))->toBe(
        '{"case_value":"pass","case_name":"High","local_value":"fail","picked":"pass","parsed":"pass",'
        .'"maybe":null,"label":"High Priority"}',
    );
});
