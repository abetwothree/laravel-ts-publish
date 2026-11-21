<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\BackedRenamedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\BackedRevaluedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Billing\Stage as BillingStage;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\MappedLevel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\PureRenamedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\PureRevaluedCases;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Shipping\Stage as ShippingStage;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\UnsafeIntegerBackedStatus;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WrapBesideCaseValueResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\UiTone;
use Workbench\App\Enums\Visibility;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
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

// K1: a wrap's alias token, with the tolki package off, rides the read channel, so only the enums read count.
it('builds a key\'s alias literals from the enums it reads, whatever it wraps, and the names its other classes print', function (
    array $read,
    array $wrapped,
    array $takenNames,
    array $literals,
) {
    expect(EnumValueTypes::absorbRules($read, $wrapped, $takenNames, [], false)[0])->toBe($literals);
})->with([
    'a read enum' => [[Priority::class], [], [], [
        'PriorityType' => [0, 1, 2, 3],
        'PriorityKind' => ['Low', 'Medium', 'High', 'Critical'],
    ]],
    'an enum the key also wraps' => [[MediaType::class], [MediaType::class], [], [
        'MediaTypeType' => ['image', 'video', 'audio', 'document', 'archive'],
        'MediaTypeKind' => ['Image', 'Video', 'Audio', 'Document', 'Archive'],
    ]],
    'an enum the key only wraps' => [[], [MediaType::class], [], []],
    'a name another class prints' => [[MediaType::class], [], ['MediaTypeType'], [
        'MediaTypeKind' => ['Image', 'Video', 'Audio', 'Document', 'Archive'],
    ]],
]);

it('reads two arms spelled alike as one only where nothing keeps them apart', function (
    array $read,
    array $classes,
    bool $keepsTokens,
    bool $queued,
    bool $dedupes,
) {
    $rules = EnumValueTypes::absorbRules($read, [], [], $classes, $keepsTokens, static fn (): bool => $queued);

    expect($rules[1])->toBe($dedupes);
})->with([
    'nothing' => [[Priority::class], [], false, false, true],
    'a token queue the key keeps' => [[Priority::class], [], true, false, false],
    'a name two of its classes print' => [[], [[User::class, 'User'], [Post::class, 'User']], false, false, false],
    'a name two of its enums print' => [[Status::class, CrmStatus::class], [], false, false, false],
    'a class queued on it later' => [[Priority::class], [], false, true, false],
]);

it('keeps two arms spelled alike apart where an enum the key wraps prints a name an enum it reads does', function () {
    $this->mock(EnumsCollector::class)->shouldReceive('collect')
        ->andReturn(collect([BillingStage::class, ShippingStage::class]));

    expect(EnumValueTypes::absorbRules([BillingStage::class], [ShippingStage::class], [], [], false))->toBe([
        ['StageType' => ['open', 'closed', 'disputed'], 'StageKind' => ['Open', 'Closed', 'Disputed']],
        false,
    ]);
});

it('asks whether a class is queued later only once nothing else keeps alike arms apart', function () {
    $asked = 0;
    $queuesLater = function () use (&$asked): bool {
        $asked++;

        return false;
    };

    EnumValueTypes::absorbRules([Priority::class], [], [], [], true, $queuesLater);
    EnumValueTypes::absorbRules([Status::class, CrmStatus::class], [], [], [], false, $queuesLater);
    expect($asked)->toBe(0);

    EnumValueTypes::absorbRules([Priority::class], [], [], [], false, $queuesLater);
    expect($asked)->toBe(1);
});

it('gives each case\'s name beside the scalar an alias spells it with, in case order', function (
    string $enum,
    string $alias,
    array $literals,
) {
    expect(EnumValueTypes::caseLiterals($enum, $alias))->toBe($literals);
})->with([
    'a backed enum\'s values' => [MediaType::class, 'MediaTypeType', [
        'Image' => 'image', 'Video' => 'video', 'Audio' => 'audio', 'Document' => 'document', 'Archive' => 'archive',
    ]],
    'an int-backed enum\'s values' => [Priority::class, 'PriorityType', ['Low' => 0, 'Medium' => 1, 'High' => 2, 'Critical' => 3]],
    'a backed enum\'s names' => [Status::class, 'StatusKind', ['Draft' => 'Draft', 'Published' => 'Published']],
    'a pure enum\'s names' => [Visibility::class, 'VisibilityType', [
        'Public' => 'Public', 'Private' => 'Private', 'Protected' => 'Protected', 'Internal' => 'Internal', 'Draft' => 'Draft',
    ]],
]);

it('gives no case literals for an alias the enum cannot use or does not spell', function (string $enum, string $alias) {
    $this->mock(EnumsCollector::class)->shouldReceive('collect')->andReturn(collect([$enum]));

    expect(EnumValueTypes::caseLiterals($enum, $alias))->toBeNull();
})->with([
    'a revalued case' => [BackedRevaluedCases::class, 'BackedRevaluedCasesType'],
    'a value past the safe integer range' => [UnsafeIntegerBackedStatus::class, 'UnsafeIntegerBackedStatusType'],
    'a pure enum\'s missing kind' => [Visibility::class, 'VisibilityKind'],
    'another enum\'s alias' => [MediaType::class, 'StatusType'],
]);

it('gives no case literals for an enum ts:publish writes no file for', function () {
    expect(EnumValueTypes::caseLiterals(BackedRenamedCases::class, 'BackedRenamedCasesType'))->toBeNull();
});

// K1: the alias a read spells beside a wrap of its enum takes the case's value in, there and in the globals file's fold.
it('publishes a case\'s value beside a wrap of its enum as the enum\'s alias', function () {
    $transformer = new ResourceTransformer(WrapBesideCaseValueResource::class);
    $types = array_map(fn (array $property): string => $property['type'], $transformer->data()->properties);

    expect($types)->toBe([
        'pair' => '{ w: AsEnum<typeof MediaType>; v: MediaTypeType }',
        'wrap_or_pair' => 'AsEnum<typeof MediaType> | { v: MediaTypeType }',
        'wrap_or_case' => "AsEnum<typeof MediaType> | 'image'",
    ])->and(TsTypeString::rewriteAsEnumToType($types['wrap_or_pair'], $transformer->globalEnumConstMap()))
        ->toBe('workbench.app.enums.MediaTypeType | { v: MediaTypeType }');
});

// With the tolki package off a wrap prints its enum's alias, which admits a case's value as a read's alias does.
it('publishes a case\'s value beside a wrap of its enum as the alias the wrap prints with the tolki package off', function () {
    config()->set('ts-publish.enums.use_tolki_package', false);

    expect(array_map(fn (array $property): string => $property['type'], new ResourceTransformer(WrapBesideCaseValueResource::class)->data()->properties))
        ->toBe([
            'pair' => '{ w: MediaTypeType; v: MediaTypeType }',
            'wrap_or_pair' => 'MediaTypeType | { v: MediaTypeType }',
            'wrap_or_case' => 'MediaTypeType',
        ]);
});
