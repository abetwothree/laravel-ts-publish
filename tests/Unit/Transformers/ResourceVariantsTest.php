<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;
use AbeTwoThree\LaravelTsPublish\Support\VariantRows;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPostCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPostDeclaredResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPostFlatResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPostMethodCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPostModelCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPostRetypedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPostSplitResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WrapOrReadPinnedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WrappedFlagCastResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Discriminated\HiddenWritesTopResource;
use Workbench\App\Http\Resources\Discriminated\NumericKeysResource;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;
use Workbench\App\Http\Resources\Discriminated\PinnedWrapsResource;
use Workbench\App\Http\Resources\Discriminated\PostPriorityResource;
use Workbench\App\Http\Resources\Discriminated\SwappedEnumsResource;
use Workbench\App\Http\Resources\Discriminated\SwappedResourcesResource;
use Workbench\App\Http\Resources\Discriminated\UnseenWritesResource;
use Workbench\App\Http\Resources\Discriminated\WrapOrReadResource;
use Workbench\App\Http\Resources\Discriminated\WrappedFlagResource;

/**
 * The data a resource publishes.
 *
 * @param  class-string<JsonResource>  $class
 */
function variantsData(string $class): TsResourceDto
{
    return new ResourceTransformer($class)->data();
}

/**
 * Each property a resource's data publishes, as `name => type`, `?` marking an optional key.
 *
 * @return array<string, string>
 */
function variantsProps(TsResourceDto $data): array
{
    $props = [];

    foreach ($data->properties as $name => $property) {
        $props[$name.($property['optional'] ? '?' : '')] = $property['type'];
    }

    return $props;
}

it('publishes a key that follows a boolean the resource sends as one arm per value', function () {
    $data = variantsData(PinnedPostResource::class);

    expect(variantsProps($data))->toBe(['id' => 'number'])
        ->and($data->variants)->toBe([
            '{ is_pinned: true; placement: PinnedPlacementResource } | { is_pinned: false; placement: FeedPlacementResource }',
        ]);
});

// A template published before variants existed reads the transformer's rows, where a reserved row is no valid key.
it('keeps a transformed resource\'s variants beside its properties, never as a reserved row', function () {
    $transformer = new ResourceTransformer(PinnedPostResource::class);

    expect(array_filter(array_keys($transformer->properties), static fn (int|string $name): bool => VariantRows::isKey((string) $name)))
        ->toBe([])
        ->and(array_keys($transformer->properties))->toBe(['id'])
        ->and($transformer->variants)->toBe([
            '{ is_pinned: true; placement: PinnedPlacementResource } | { is_pinned: false; placement: FeedPlacementResource }',
        ]);
});

it('publishes a nullable enum\'s values as the literals each arm sends, importing no alias no arm spells', function () {
    $data = variantsData(PostPriorityResource::class);

    expect(variantsProps($data))->toBe(['id' => 'number'])
        ->and($data->variants)->toBe([
            '{ priority: 0 | 1; badge: null; escalated_at?: never } | { priority: 2; badge: string; escalated_at?: never }'
            .' | { priority: 3; badge: UserResource; escalated_at: string | null }'
            .' | { priority: null; badge: never[]; escalated_at?: never }',
        ])
        ->and($data->typeImports)->toBe(['..' => ['UserResource']]);
});

it('keeps each arm\'s own wraps, aliases and resources, importing what the flat keys import', function () {
    $data = variantsData(PinnedWrapsResource::class);

    expect($data->properties)->toBe([])
        ->and($data->variants)->toBe([
            '{ is_pinned: true; state: AsEnum<typeof WorkbenchStatus>; stage: WorkbenchStatusType; author: UserResource }'
            .' | { is_pinned: false; state: AsEnum<typeof Visibility> | null; stage: CrmStatusType; author?: never }',
        ])
        ->and($data->valueImports)->toBe(['../../../enums' => ['Status as WorkbenchStatus', 'Visibility']])
        ->and($data->typeImports)->toBe([
            '../../../../crm/enums' => ['StatusType as CrmStatusType'],
            '../../../enums' => ['StatusType as WorkbenchStatusType'],
            '..' => ['UserResource'],
        ]);
});

it('publishes the keys flat where a cast, the attribute or the config rules the arms out', function (Closure $publish) {
    $data = $publish();

    expect(variantsProps($data))->toBe([
        'id' => 'number',
        'is_pinned' => 'boolean',
        'placement' => 'PinnedPlacementResource | FeedPlacementResource',
    ])->and($data->variants)->toBe([]);
})->with([
    'a class cast on the discriminant' => fn (): TsResourceDto => variantsData(PinnedPostCastResource::class),
    'a toArray() cast on a dependent' => fn (): TsResourceDto => variantsData(PinnedPostMethodCastResource::class),
    'a model cast on the discriminant' => fn (): TsResourceDto => variantsData(PinnedPostModelCastResource::class),
    'the attribute off' => fn (): TsResourceDto => variantsData(PinnedPostFlatResource::class),
    'the config off' => function (): TsResourceDto {
        config(['ts-publish.resources.discriminated_unions' => false]);

        return variantsData(PinnedPostResource::class);
    },
]);

it('publishes the arms of a resource whose attribute turns them on while the config is off', function () {
    config(['ts-publish.resources.discriminated_unions' => false]);

    $data = variantsData(PinnedPostSplitResource::class);

    expect(variantsProps($data))->toBe(['id' => 'number'])
        ->and($data->variants)->toBe([
            '{ is_pinned: true; placement: PinnedPlacementResource } | { is_pinned: false; placement: FeedPlacementResource }',
        ]);
});

// D2: a declared type stands beside the arms the body proves, so only a `@return` that changes a grouped row ends it.
it('publishes the keys flat under a `@return` that changes a row they follow, and arms under one that leaves it', function () {
    $retyped = variantsData(PinnedPostRetypedResource::class);
    $declared = variantsData(PinnedPostDeclaredResource::class);

    expect(variantsProps($retyped))->toBe([
        'id' => 'number',
        'is_pinned' => 'boolean',
        'placement?' => 'PinnedPlacementResource | FeedPlacementResource',
    ])->and($retyped->variants)->toBe([])
        ->and(variantsProps($declared))->toBe(['id' => 'number'])
        ->and($declared->variants)->toBe([
            '{ is_pinned: true; placement: PinnedPlacementResource } | { is_pinned: false; placement: FeedPlacementResource }',
        ]);
});

// Only the tolki package prints an enum resource apart from a direct read of its enum, as `AsEnum<typeof X>`; without
// it both print the enum's type, so neither the value nor its key's arms tell them apart.
it('publishes a wrap and a direct read of one enum in one arm where the tolki package is off', function () {
    config()->set('ts-publish.enums.use_tolki_package', false);
    $data = variantsData(WrapOrReadResource::class);
    $arms = '{ priority: 0 | 1; status: StatusType; tally: number } | { priority: 2 | 3 | null; status: null; tally: string }';

    expect(variantsProps($data))->toBe(['is_pinned' => 'boolean', 'stage' => 'StatusType', 'reordered' => $arms])
        ->and($data->variants)->toBe([$arms])
        ->and(variantsData(WrapOrReadPinnedResource::class)->variants)->toBe([])
        ->and(variantsProps(variantsData(WrapOrReadPinnedResource::class)))->toBe(['is_pinned' => 'boolean', 'stage' => 'StatusType']);
});

// PHP turns a numeric key's name into an int wherever the name keys an array, so each arm must take it back as a name.
it('keeps a numeric key\'s name as the discriminant or a dependent, at the top level and nested', function () {
    $data = variantsData(NumericKeysResource::class);

    expect(variantsProps($data))->toBe([
        'nested' => '{ pinned: true; "0": string; "7": 0; note: string } | { pinned: true; "0": string; "7": 1; note: number }'
            .' | { pinned: false; "0": null; "7": 0; note: string } | { pinned: false; "0": null; "7": 1; note: number }',
    ])->and($data->variants)->toBe([
        '{ is_pinned: true; "0": string } | { is_pinned: false; "0": null }',
        '{ "7": 0; note: string } | { "7": 1; note: number }',
    ]);
});

// Order and multiplicity bind each token to its class, so arms spelling one text for swapped classes stay apart.
it('keeps arms that bind two same-named resources in swapped order apart, at the top level and nested', function () {
    $data = variantsData(SwappedResourcesResource::class);
    $low = '{ primary: WorkbenchUserResource; secondary: CrmUserResource }';
    $medium = '{ primary: CrmUserResource; secondary: WorkbenchUserResource }';

    expect(variantsProps($data))->toBe([
        'meta' => "{ priority: 0; pair: {$low} } | { priority: 1; pair: {$medium} } | { priority: 2 | 3 | null; pair: null }",
    ])->and($data->variants)->toBe([
        "{ priority: 0; pair: {$low} } | { priority: 1; pair: {$medium} } | { priority: 2 | 3 | null; pair: null }",
    ]);
});

// An enum nested in a value is queued again after the object's members, which a union of objects reads as the next
// object's token wherever two enums print one name.
it('publishes flat each literal whose objects hold two same-named enums, one of them nested', function () {
    $data = variantsData(SwappedEnumsResource::class);
    $swapped = '{ primary: WorkbenchStatusType; secondary: CrmStatusType } | { primary: CrmStatusType; secondary: WorkbenchStatusType }';

    expect(variantsProps($data))->toBe([
        'priority' => 'PriorityType | null',
        'stages' => "{$swapped} | null",
        'meta' => '{ priority: PriorityType | null; stages: { primary: WorkbenchStatusType; secondary: CrmStatusType }'
            .' | { primary: CrmStatusType; secondary: WorkbenchStatusType; extra: boolean } | null }',
        'shared' => '{ status: WorkbenchStatusType; is_pinned: boolean; lead: { stage: CrmStatusType } | null }',
    ])->and($data->variants)->toBe([]);
});

// PHP tests the object a guard reads, while the override types what json_encode() writes, so no such read decides arms.
it('publishes flat the keys a read typed by a class override or a custom cast picks', function () {
    expect(variantsProps(variantsData(WrappedFlagResource::class)))->toBe([
        'flag' => 'boolean',
        'flag_title' => 'string | null',
        'mode' => "'on' | 'off'",
        'mode_title' => 'string | null',
    ])->and(variantsProps(variantsData(WrappedFlagCastResource::class)))->toBe([
        'is_pinned' => 'boolean',
        'pinned_title' => 'string | null',
        'status' => 'boolean',
        'status_title' => 'string | null',
    ])->and(variantsData(WrappedFlagResource::class)->variants)->toBe([])
        ->and(variantsData(WrappedFlagCastResource::class)->variants)->toBe([])
        ->and(variantsData(PinnedPostResource::class)->variants)->not->toBe([]);
});

// A guard after a write may test another value than the discriminant sent, wherever the literal spells that write.
it('publishes flat a literal whose read a helper, the model or a `->` write rewrites between its keys', function () {
    expect(variantsProps(variantsData(UnseenWritesResource::class)))->toBe([
        'by_helper' => '{ is_pinned: boolean; reset: boolean; title: string | null }',
        'by_proxy' => '{ is_pinned: boolean; reset: boolean; title: string | null }',
        'by_arrow' => '{ enabled: boolean | null; off: boolean; title: string | null }',
        'by_reader' => '{ is_pinned: true; label: string; title: string } | { is_pinned: false; label: string; title: null }',
    ]);
});

// `level`'s `null` arm sets the priority before `check` reads it, so `check` may pass for a `null` PHP still sends.
it('publishes flat a top-level literal whose read a call in an arm rewrites before a later guard', function () {
    $data = variantsData(HiddenWritesTopResource::class);

    expect(variantsProps($data))->toBe([
        'is_pinned' => 'boolean',
        'changed' => 'string | null',
        'detail' => 'string | null',
        'priority' => 'PriorityType | null',
        'level' => 'string | boolean | null',
        'check' => 'string',
    ])->and($data->variants)->toBe([]);
});
