<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\UnsafeIntegerBackedStatus;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\EnumInterfaceReads;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\PostHelperParamsResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\EnumValuesPayload;

beforeEach(function () {
    PublishedEnums::flush();
});

it('types a backed enum value read as the published alias or the literal union', function () {
    expect(analyzedKeyTypes(EnumValuesPayload::class))->toBe([
        'grade' => 'GradeType | null',
        'fallback_grade' => 'GradeType',
        'grade_name' => "'Pass' | 'Fail'",
        'priority' => 'PriorityType',
        'priority_name' => "'Low' | 'Medium' | 'High' | 'Critical'",
        'visibility' => 'VisibilityType',
        'color' => "'red' | 'green' | 'blue' | 'amber' | 'gray' | 'purple'",
        'color_name' => "'Red' | 'Green' | 'Blue' | 'Amber' | 'Gray' | 'Purple'",
        'excluded' => "'foo' | 'bar'",
        'excluded_name' => "'Foo' | 'Bar'",
        'grade_list' => 'GradeType[]',
    ])->and(analyzedImportNames(EnumValuesPayload::class))->toBe(['GradeType', 'PriorityType', 'VisibilityType']);
});

it('sends values the published types admit', function () {
    expect(json_encode(new EnumValuesPayload()->toArray()))->toBe(
        '{"grade":null,"fallback_grade":"pass","grade_name":"Pass","priority":0,"priority_name":"Low",'
        .'"visibility":"Public","color":"amber","color_name":"Amber","excluded":"foo","excluded_name":"Foo",'
        .'"grade_list":["pass"]}',
    );
});

it('publishes the literal union when enum publishing is off or excludes the enum', function (array $config) {
    config()->set($config);

    expect(analyzedKeyTypes(EnumValuesPayload::class)['fallback_grade'])->toBe("'pass' | 'fail'")
        ->and(analyzedImportNames(EnumValuesPayload::class))->not->toContain('GradeType');
})->with([
    'publishing off' => [['ts-publish.enums.enabled' => false]],
    'excluded' => [['ts-publish.enums.excluded' => [Grade::class]]],
]);

it('publishes the literal union for an enum whose cases #[TsCase] renames or revalues', function () {
    expect(EnumValueTypes::value(Color::class, true)['type'])->toBe("'red' | 'green' | 'blue' | 'amber' | 'gray' | 'purple'")
        ->and(EnumValueTypes::name(Color::class, true)['type'])->toBe("'Red' | 'Green' | 'Blue' | 'Amber' | 'Gray' | 'Purple'")
        ->and(EnumValueTypes::value(Color::class, true))->not->toHaveKey('directEnumFqcn');
});

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
