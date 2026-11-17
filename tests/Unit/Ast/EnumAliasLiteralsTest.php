<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\BackedRenamedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\BackedRevaluedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Billing\Stage as BillingStage;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\MappedLevel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\PureRenamedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\PureRevaluedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Shipping\Stage as ShippingStage;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\UnsafeIntegerBackedStatus;
use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\UiTone;
use Workbench\App\Enums\Visibility;
use Workbench\Crm\Enums\Status as CrmStatus;

beforeEach(function () {
    PublishedEnums::flush();
});

it('reads a backed enum\'s values as its type alias admits them and its names as its kind alias does', function () {
    expect(EnumValueTypes::aliasLiterals([MediaType::class]))->toBe([
        'MediaTypeType' => ['image', 'video', 'audio', 'document', 'archive'],
        'MediaTypeKind' => ['Image', 'Video', 'Audio', 'Document', 'Archive'],
    ]);
});

it('keeps an int-backed enum\'s values as ints', function () {
    expect(EnumValueTypes::aliasLiterals([Priority::class]))->toBe([
        'PriorityType' => [0, 1, 2, 3],
        'PriorityKind' => ['Low', 'Medium', 'High', 'Critical'],
    ]);
});

it('reads a pure enum\'s names as its type alias admits them, with no kind alias', function () {
    expect(EnumValueTypes::aliasLiterals([Visibility::class]))->toBe([
        'VisibilityType' => ['Public', 'Private', 'Protected', 'Internal', 'Draft'],
    ]);
});

it('admits only the literals both same-named enums admit under the alias they share', function () {
    expect(EnumValueTypes::aliasLiterals([Status::class, CrmStatus::class]))->toBe([
        'StatusType' => [],
        'StatusKind' => [],
    ]);
});

it('admits under an alias two same-named enums spell the literals both admit', function () {
    $this->mock(EnumsCollector::class)->shouldReceive('collect')
        ->andReturn(collect([BillingStage::class, ShippingStage::class]));

    expect(EnumValueTypes::aliasLiterals([BillingStage::class, ShippingStage::class]))->toBe([
        'StageType' => ['open', 'closed'],
        'StageKind' => ['Open', 'Closed'],
    ]);
});

it('reads an enum listed twice as once', function () {
    expect(EnumValueTypes::aliasLiterals([MediaType::class, MediaType::class]))
        ->toBe(EnumValueTypes::aliasLiterals([MediaType::class]));
});

it('admits nothing for an alias #[TsCase] or an unsafe integer makes spell other than what PHP sends', function (
    string $enum,
    array $literals,
) {
    $this->mock(EnumsCollector::class)->shouldReceive('collect')->andReturn(collect([$enum]));

    expect(EnumValueTypes::aliasLiterals([$enum]))->toBe($literals);
})->with([
    'a backed enum with a revalued case keeps its kind' => [
        BackedRevaluedCases::class, ['BackedRevaluedCasesKind' => ['Visible', 'Hidden']],
    ],
    'a backed enum with a renamed case keeps its type' => [
        BackedRenamedCases::class, ['BackedRenamedCasesType' => ['visible', 'hidden']],
    ],
    'a pure enum with a renamed case keeps its type, which a rename leaves' => [
        PureRenamedCases::class, ['PureRenamedCasesType' => ['Visible', 'Hidden']],
    ],
    'a pure enum with a revalued case' => [PureRevaluedCases::class, []],
    'an enum with a value past the safe integer range' => [UnsafeIntegerBackedStatus::class, []],
]);

it('admits nothing for an enum ts:publish writes no file for', function () {
    expect(EnumValueTypes::aliasLiterals([BackedRenamedCases::class]))->toBe([]);
});

it('admits nothing under a usable alias a same-named enum spells but cannot use', function () {
    $this->mock(EnumsCollector::class)->shouldReceive('collect')->andReturn(collect([Status::class]));

    expect(EnumValueTypes::aliasLiterals([Status::class, CrmStatus::class]))->toBe([
        'StatusType' => [],
        'StatusKind' => [],
    ]);
});

it('keeps an overridden enum\'s aliases, which its value and name reads still spell', function () {
    expect(EnumValueTypes::aliasLiterals([UiTone::class]))->toBe([
        'UiToneType' => ['bright', 'dim'],
        'UiToneKind' => ['Bright', 'Dim'],
    ]);
});

it('admits nothing under an alias the enum\'s own override is spelled like', function () {
    config()->set('ts-publish.custom_ts_mappings', [MappedLevel::class => 'MappedLevelType']);
    $this->mock(EnumsCollector::class)->shouldReceive('collect')->andReturn(collect([MappedLevel::class]));

    expect(EnumValueTypes::aliasLiterals([MappedLevel::class]))->toBe(['MappedLevelKind' => ['Low', 'High']]);
});

it('leaves out an alias a model or resource on the same key publishes under', function () {
    expect(EnumValueTypes::aliasLiterals([MediaType::class], ['MediaTypeType']))->toBe([
        'MediaTypeKind' => ['Image', 'Video', 'Audio', 'Document', 'Archive'],
    ]);
});

it('admits nothing for no enum', function () {
    expect(EnumValueTypes::aliasLiterals([]))->toBe([]);
});

describe('EnumValueTypes::channelAliasLiterals()', function () {
    beforeEach(function () {
        $this->mock(EnumsCollector::class)->shouldReceive('collect')
            ->andReturn(collect([BillingStage::class, ShippingStage::class, MediaType::class]));
    });

    test('reads the enums read directly as aliasLiterals() does', function () {
        expect(EnumValueTypes::channelAliasLiterals([BillingStage::class, ShippingStage::class], [], [], []))
            ->toBe(EnumValueTypes::aliasLiterals([BillingStage::class, ShippingStage::class]));
    });

    test('admits nothing for an enum also wrapped in an EnumResource', function () {
        expect(EnumValueTypes::channelAliasLiterals([MediaType::class], [MediaType::class], [], []))->toBe([]);
    });

    test('admits nothing under a name a wrapped enum spells too', function () {
        expect(EnumValueTypes::channelAliasLiterals([BillingStage::class], [ShippingStage::class], [], []))->toBe([
            'StageType' => [],
            'StageKind' => [],
        ]);
    });

    test('leaves out a name a model or resource publishes under', function () {
        expect(EnumValueTypes::channelAliasLiterals(
            [MediaType::class],
            [],
            ['Some\\Models\\MediaTypeType'],
            ['Some\\Http\\Resources\\MediaTypeKind'],
        ))->toBe([]);
    });
});
