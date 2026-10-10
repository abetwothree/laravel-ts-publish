<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\BranchCastsOptionalApartResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CarriedSpelledResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastBareBesideWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastCarriedModelEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastCarriedSpelledEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastDisplacedOwnNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastDroppedEnumBesideSharedNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastKeyofTypeofImportResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastModelEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastModelNoImportEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastMorphUnionResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastOneOfTwoWrapsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastOverAttributeImportEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastOverAttributeImportOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastOverAttributeImportResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastResourceEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastSpelledMorphUnionResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastSpelledTwoClassAccessorOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastSpelledTwoEnumAccessorKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastStringMorphUnionResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastStringTwoEnumAccessorOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastTwoClassAccessorOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastTwoEnumAccessorOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastTypeofWithoutWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastWrapTypeImportResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InlineCarriedEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InlineCarriedModelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InlineSpreadCastCarriedEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InlineSpreadCastEnumEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InlineSpreadCastEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InlineSpreadCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InlineSpreadCastWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastDisplacedOwnNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastMixedTernaryResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastModelEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastModelNoImportEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastMorphUnionResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastOverAttributeImportResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastTwoClassAccessorResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastTwoEnumAccessorOnlyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastTwoEnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastWrapTypeImportResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodSameBasenameOverrideResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ModelOptionalVsClassCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ModelOptionalVsMethodCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ModelOptionalVsRequiredMethodCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ModelVsMethodCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\NonPayloadCastImportEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\OneBranchCastSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\OverriddenMethodCastImportEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\RewrittenSpreadKeyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SameBasenameOverrideResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SiblingSpreadCastsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SiblingSpreadCastsReversedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SpreadCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SpreadOverCastSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SpreadOverSpreadEnumResource;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Events\ReviewerCastEvent;
use Workbench\App\Http\Resources\ImageReviewCastResource;
use Workbench\App\Http\Resources\PostSpelledCastResource;
use Workbench\App\Http\Resources\WarehouseContactCastResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * The import lines and property lines a resource's published file holds.
 *
 * @param  class-string  $resource
 * @return list<string>
 */
function castInForceLines(string $resource): array
{
    config()->set('ts-publish.output_to_files', false);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer($resource));

    return array_values(preg_grep('/^(import |    [A-Za-z0-9_"]+\??: )/', explode("\n", $content)) ?: []);
}

beforeEach(fn () => config()->set('ts-publish.enums.use_tolki_package', true));

// #87: a cast that spells one of its key's enum type names is the key's text, never rewritten to the `AsEnum` wrap.
describe('a cast that spells an enum resource\'s own type', function () {
    it('publishes the workbench resource\'s casts as written, importing only the types they spell', function () {
        expect(castInForceLines(PostSpelledCastResource::class))->toBe([
            "import type { StatusType, VisibilityType } from '../../enums';",
            '    id: number;',
            '    status: StatusType;',
            '    visibility: VisibilityType;',
            '    either: StatusType | VisibilityType | null;',
            '    mixed: StatusType | null;',
        ]);
    });

    it('publishes each cast as written, with only the imports its text needs', function (string $resource, array $lines) {
        expect(castInForceLines($resource))->toBe($lines);
    })->with([
        'the cast imports the type it spells' => [
            CastWrapTypeImportResource::class,
            ["import type { StatusType } from '@js/types/status';", '    k: StatusType;'],
        ],
        'the cast on `toArray()` imports the type it spells' => [
            MethodCastWrapTypeImportResource::class,
            ["import type { StatusType } from '@js/types/status';", '    k: StatusType;'],
        ],
        'the cast spells one of the two enums its ternary wraps' => [
            CastOneOfTwoWrapsResource::class,
            ["import type { StatusType } from '../../../../workbench/app/enums';", '    k: StatusType | null;'],
        ],
        'the cast writes the const after `typeof` without the wrap' => [
            CastTypeofWithoutWrapResource::class,
            ["import { Status } from '../../../../workbench/app/enums';", '    k: (typeof Status)[keyof typeof Status];'],
        ],
        'the cast writes `keyof typeof` with its own import' => [
            CastKeyofTypeofImportResource::class,
            ["import type { Status } from '@js/types/enums';", '    k: keyof typeof Status;'],
        ],
    ]);

    // No cast is involved: the later spread's value takes the key, and the earlier spread's wrap must not be rewritten.
    it('publishes a later spread\'s key over an earlier spread\'s enum resource with no wrap and no import', function () {
        expect(castInForceLines(SpreadOverSpreadEnumResource::class))->toBe(['    k: string;']);
    });

    it('leaves a method-level cast over a mixed ternary unwrapped through AstEngine::analyze()', function () {
        $result = resolve(AstEngine::class)->analyze(MethodCastMixedTernaryResource::class, 'toArray', null, 'tests/fixtures');

        expect(array_column($result->properties, 'type', 'name'))->toBe(['k' => 'string'])
            ->and($result->typeImports)->toBe([])
            ->and($result->valueImports)->toBe([]);
    });
});

// #111: a class a cast displaced is not imported, whichever cast displaced it and whatever kind of class it is.
describe('a cast over a key whose classes it displaces', function () {
    it('publishes the workbench resource over a resource union with no resource import', function () {
        expect(castInForceLines(ImageReviewCastResource::class))->toBe([
            "import type { ReviewSubject } from '@js/types/reviews';",
            '    id: number;',
            '    reviewable?: ReviewSubject | null;',
        ]);
    });

    it('publishes the workbench resource over a CRM model and an `only()` accessor with only the app\'s model', function () {
        expect(castInForceLines(WarehouseContactCastResource::class))->toBe([
            "import type { User } from '../../models';",
            '    id: number;',
            '    manager: User | null;',
            '    contact: { id: number; name: string } | null;',
            '    review_priority: string;',
        ]);
    });

    it('imports only what each cast\'s text needs', function (string $resource, array $lines) {
        expect(castInForceLines($resource))->toBe($lines);
    })->with([
        'a class-level cast with an import over a resource union' => [
            CastMorphUnionResource::class,
            ["import type { UserResource } from '@js/types/user';", '    id: number;', '    reviewable?: UserResource | null;'],
        ],
        'a method-level cast with an import over a resource union' => [
            MethodCastMorphUnionResource::class,
            ["import type { UserResource } from '@js/types/user';", '    id: number;', '    reviewable?: UserResource | null;'],
        ],
        'a cast without an import over a resource union' => [
            CastStringMorphUnionResource::class,
            ['    reviewable?: { id: number } | null;'],
        ],
        'a cast without an import that spells the name a resource union\'s resources share' => [
            CastSpelledMorphUnionResource::class,
            ["import type { UserResource } from '../../../../workbench/crm/http/resources';", '    reviewable?: UserResource | null;'],
        ],
        'a method-level cast with an import over a two-enum accessor' => [
            MethodCastTwoEnumResource::class,
            [
                "import type { StatusType } from '@js/types/status';",
                "import type { Image } from '../../../../workbench/app/models';",
                "import type { StatusType as CrmStatusType } from '../../../../workbench/crm/enums';",
                '    id: number;',
                '    review_priority: StatusType | null;',
                '    crm_contact_partial: { status: CrmStatusType; images: Image[] } | null;',
            ],
        ],
        'a class-level cast with an import over a two-enum `only()` accessor' => [
            CastTwoEnumAccessorOnlyResource::class,
            [
                "import type { StatusType } from '@js/types/status';",
                "import type { Image } from '../../../../workbench/app/models';",
                "import type { StatusType as CrmStatusType } from '../../../../workbench/crm/enums';",
                '    review_priority: StatusType | null;',
                '    crm_contact_partial: { status: CrmStatusType; images: Image[] } | null;',
            ],
        ],
        'a method-level cast with an import over a two-enum `only()` accessor' => [
            MethodCastTwoEnumAccessorOnlyResource::class,
            [
                "import type { StatusType } from '@js/types/status';",
                "import type { Image } from '../../../../workbench/app/models';",
                "import type { StatusType as CrmStatusType } from '../../../../workbench/crm/enums';",
                '    review_priority: StatusType | null;',
                '    crm_contact_partial: { status: CrmStatusType; images: Image[] } | null;',
            ],
        ],
        'a cast without an import over a two-enum `only()` accessor' => [
            CastStringTwoEnumAccessorOnlyResource::class,
            ['    review_priority: string;'],
        ],
        // No import channel reaches the key, so the accessor's late registration binds the token to its first class.
        'a cast without an import that adds a two-enum accessor\'s key and spells the name its enums share' => [
            CastSpelledTwoEnumAccessorKeyResource::class,
            [
                "import type { StatusType } from '../../../../workbench/app/enums';",
                '    id: number;',
                '    review_priority: StatusType | null;',
            ],
        ],
        'a method-level cast with an import over a two-model accessor' => [
            MethodCastTwoClassAccessorResource::class,
            ["import type { User } from '@js/types/user';", '    last_user_activity_by_typed: User | null;'],
        ],
        'a class-level cast with an import over a two-model `only()` accessor' => [
            CastTwoClassAccessorOnlyResource::class,
            ["import type { User } from '@js/types/user';", '    last_user_activity_by_typed: User | null;'],
        ],
        // `only()` drops a two-model accessor's import channels, so its late registration binds the first class.
        'a cast without an import that spells the name a two-model `only()` accessor\'s models share' => [
            CastSpelledTwoClassAccessorOnlyResource::class,
            [
                "import type { User } from '../../../../workbench/crm/models';",
                '    last_user_activity_by_typed: User | null;',
            ],
        ],
        'a class-level cast with an import named like the enum it displaces' => [
            CastDisplacedOwnNameResource::class,
            ["import type { RoleType } from '@js/types/role';", '    role: RoleType | null;'],
        ],
        'a method-level cast with an import named like the enum it displaces' => [
            MethodCastDisplacedOwnNameResource::class,
            ["import type { RoleType } from '@js/types/role';", '    role: RoleType | null;'],
        ],
        'a cast with an import named like the `#[TsType]` import of an `only()` attribute' => [
            CastOverAttributeImportOnlyResource::class,
            ["import type { MenuSettingsType } from '@js/types/menu';", '    menu_config: MenuSettingsType | null;'],
        ],
        'a cast with an import named like the `#[TsType]` import of an attribute its key reads' => [
            CastOverAttributeImportResource::class,
            ["import type { MenuSettingsType } from '@js/types/menu';", '    menu_config: MenuSettingsType | null;'],
        ],
        'a method-level cast with an import named like the `#[TsType]` import of an attribute its key reads' => [
            MethodCastOverAttributeImportResource::class,
            ["import type { MenuSettingsType } from '@js/types/menu';", '    menu_config: MenuSettingsType | null;'],
        ],
        'a cast over a bare enum read beside a wrap of the same enum' => [
            CastBareBesideWrapResource::class,
            [
                "import { type AsEnum } from '@tolki/ts';",
                "import { Status } from '../../../../workbench/app/enums';",
                '    k: string;',
                '    a: AsEnum<typeof Status>;',
            ],
        ],
    ]);

    it('drops the enum a cast displaced beside another enum of its type name, with the tolki package off', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        expect(castInForceLines(CastDroppedEnumBesideSharedNameResource::class))->toBe([
            "import type { StatusType } from '../../../../workbench/crm/enums';",
            '    k: string;',
            '    crm: StatusType | null;',
        ]);
    });
});

// #111: a spread helper's cast is final for the key it publishes, and the last spread's cast is the one in force.
describe('a cast a spread helper declares', function () {
    it('publishes each spread cast as written, aliasing only the package\'s own names', function (string $resource, array $lines) {
        expect(castInForceLines($resource))->toBe($lines);
    })->with([
        'a helper spread at the top level' => [
            SpreadCastResource::class,
            [
                "import type { User } from '@js/types/user';",
                "import type { User as CrmUser } from '../../../../workbench/crm/models';",
                '    manager: User | null;',
                '    crm: CrmUser | null;',
            ],
        ],
        'a helper spread inside an inline array' => [
            InlineSpreadCastResource::class,
            [
                "import type { User } from '@js/types/user';",
                "import type { User as CrmUser } from '../../../../workbench/crm/models';",
                '    nested: { manager: User | null; x: number };',
                '    crm: CrmUser | null;',
            ],
        ],
        'a helper spread inside an inline array, before a sibling enum of its type name' => [
            InlineSpreadCastEnumResource::class,
            [
                "import type { StatusType as WorkbenchStatusType } from '../../../../workbench/app/enums';",
                "import type { StatusType as CrmStatusType } from '../../../../workbench/crm/enums';",
                '    nested: { status: WorkbenchStatusType | null; crm: CrmStatusType | null };',
            ],
        ],
        'a helper spread inside an inline array, over the enum its key wraps' => [
            InlineSpreadCastWrapResource::class,
            ["import type { StatusType } from '../../../../workbench/app/enums';", '    nested: { status: StatusType | null };'],
        ],
        'a helper spread inside an inline array, whose cast on another key spells the enum it displaced' => [
            InlineSpreadCastCarriedEnumResource::class,
            ["import type { StatusType } from '../../../../workbench/app/enums';", '    nested: { status: string; label: StatusType[] };'],
        ],
        'two helpers, the later one without an import' => [
            SiblingSpreadCastsResource::class,
            [
                "import type { User as WorkbenchUser } from '../../../../workbench/app/models';",
                "import type { User as CrmUser } from '../../../../workbench/crm/models';",
                '    manager: WorkbenchUser | null;',
                '    crm: CrmUser | null;',
            ],
        ],
        'two helpers, the later one with an import' => [
            SiblingSpreadCastsReversedResource::class,
            [
                "import type { User } from '@js/types/user';",
                "import type { User as CrmUser } from '../../../../workbench/crm/models';",
                '    manager: User | null;',
                '    crm: CrmUser | null;',
            ],
        ],
    ]);

    it('keeps an inline cast member\'s enum before a sibling enum of its type name, with the tolki package off', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        expect(castInForceLines(InlineSpreadCastEnumResource::class))->toContain(
            '    nested: { status: WorkbenchStatusType | null; crm: CrmStatusType | null };',
        );
    });

    it('publishes an inline array\'s spread cast as written through AstEngine::analyze()', function () {
        $result = resolve(AstEngine::class)->analyze(InlineSpreadCastResource::class, 'toArray', null, 'tests/fixtures');

        expect(array_column($result->properties, 'type', 'name'))->toBe([
            'nested' => '{ manager: User | null; x: number }',
            'crm' => 'CrmUser | null',
        ])->and($result->typeImports)->toBe([
            '@js/types/user' => ['User'],
            '../../workbench/crm/models' => ['User as CrmUser'],
        ]);
    });
});

// #111: an event publishes a cast as written, never wrapped in `Partial<>` or aliased by the value it displaced, and
// its import never shares a name with a model's.
describe('a cast on a broadcast event', function () {
    it('publishes the workbench event\'s method-level cast as written, with no model import for it', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => ReviewerCastEvent::class]);

        expect(array_column($transformer->properties, 'type'))->toBe(['ReviewerCard | null', 'Partial<User>'])
            ->and($transformer->typeImports)->toBe([
                '@js/types/reviews' => ['ReviewerCard'],
                '../../crm/models' => ['User'],
            ]);
    });

    it('publishes each cast as written, aliasing only a cast without an import', function (string $event, array $types, array $imports) {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => $event]);

        expect(array_column($transformer->properties, 'type'))->toBe($types)
            ->and($transformer->typeImports)->toBe($imports);
    })->with([
        'a class-level cast with an import' => [
            CastModelEvent::class,
            ['User | null', 'Partial<CrmUser>'],
            ['@js/types/user' => ['User'], '../../../../workbench/crm/models' => ['User as CrmUser']],
        ],
        'a class-level cast without an import' => [
            CastModelNoImportEvent::class,
            ['AppUser | null', 'Partial<CrmUser>'],
            ['../../../../workbench/app/models' => ['User as AppUser'], '../../../../workbench/crm/models' => ['User as CrmUser']],
        ],
        'a `broadcastWith()` cast with an import' => [
            MethodCastModelEvent::class,
            ['User | null', 'Partial<CrmUser>'],
            ['@js/types/user' => ['User'], '../../../../workbench/crm/models' => ['User as CrmUser']],
        ],
        'a `broadcastWith()` cast without an import' => [
            MethodCastModelNoImportEvent::class,
            ['AppUser | null', 'Partial<CrmUser>'],
            ['../../../../workbench/app/models' => ['User as AppUser'], '../../../../workbench/crm/models' => ['User as CrmUser']],
        ],
    ]);

    it('aliases an inline cast member\'s enum apart from a sibling enum of its type name', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => InlineSpreadCastEnumEvent::class]);

        expect(array_column($transformer->properties, 'type'))->toBe(['{ status: AppStatusType | null; crm: CrmStatusType | null }'])
            ->and($transformer->typeImports)->toBe([
                '../../../../workbench/app/enums' => ['StatusType as AppStatusType'],
                '../../../../workbench/crm/enums' => ['StatusType as CrmStatusType'],
            ]);
    });

    it('imports no resource a cast displaced', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CastResourceEvent::class]);

        expect(array_column($transformer->properties, 'type'))->toBe(['{ id: number }'])
            ->and($transformer->typeImports)->toBe([]);
    });
});

// A cast entry describes one value of its key: a later spread or another return branch that sets the key without it
// publishes a value the cast never described.
describe('a cast entry and the value it describes', function () {
    it('fits a key only to the cast its published value carries', function (string $resource, array $lines) {
        expect(castInForceLines($resource))->toBe($lines);
    })->with([
        'a later spread sets the key without a cast' => [
            SpreadOverCastSpreadResource::class,
            [
                "import type { User as WorkbenchUser } from '../../../../workbench/app/models';",
                "import type { User as CrmUser } from '../../../../workbench/crm/models';",
                '    owner: WorkbenchUser | null;',
                '    crm: CrmUser | null;',
            ],
        ],
        'only one of two return branches casts the key' => [
            OneBranchCastSpreadResource::class,
            [
                "import type { User as WorkbenchUser } from '../../../../workbench/app/models';",
                "import type { User as CrmUser } from '../../../../workbench/crm/models';",
                '    owner: WorkbenchUser | string | null;',
                '    crm: CrmUser | null;',
            ],
        ],
    ]);
});

// A class a cast displaced and its text does not spell is carried apart from every queue, and imported only for a text
// that spells its name with no class of its own behind it.
describe('a class a cast carries', function () {
    it('imports it only for a text no class of its name stands behind', function (string $resource, array $lines) {
        expect(castInForceLines($resource))->toBe($lines);
    })->with([
        'an inline member\'s carried enum beside a sibling enum of its type name' => [
            InlineCarriedEnumResource::class,
            ["import type { StatusType } from '../../../../workbench/crm/enums';", '    nested: { status: string; crm: StatusType | null };'],
        ],
        'an inline member\'s carried class, spelled by a sibling cast without an import' => [
            InlineCarriedModelResource::class,
            [
                "import type { StatusType } from '../../../../workbench/app/enums';",
                "import type { User } from '../../../../workbench/app/models';",
                '    nested: { owner: string; label: User | null };',
                '    maybe: { state: string; tag: StatusType } | null;',
            ],
        ],
        'a carried model, enum and resource, each spelled by a cast without an import, beside a wrap of the enum' => [
            CarriedSpelledResource::class,
            [
                "import { type AsEnum } from '@tolki/ts';",
                "import { Status } from '../../../../workbench/app/enums';",
                "import type { StatusType } from '../../../../workbench/app/enums';",
                "import type { TeamResource } from '../../../../workbench/app/http/resources';",
                "import type { User } from '../../../../workbench/app/models';",
                '    owner: string;',
                '    state: string;',
                '    team: string;',
                '    wrapped: AsEnum<typeof Status> | null;',
                '    label: User | null;',
                '    tag: StatusType;',
                '    ref: TeamResource | null;',
            ],
        ],
    ]);

    it('imports each carried class a method cast\'s text spells, through AstEngine::analyze()', function () {
        $result = resolve(AstEngine::class)->analyze(CarriedSpelledResource::class, 'toArray', null, 'tests/fixtures');

        expect($result->typeImports)->toBe([
            '../../workbench/app/enums' => ['StatusType'],
            '../../workbench/app/http/resources' => ['TeamResource'],
            '../../workbench/app/models' => ['User'],
        ]);
    });

    it('imports an inline member\'s carried enum a sibling cast spells, through AstEngine::analyze()', function () {
        $result = resolve(AstEngine::class)->analyze(InlineSpreadCastCarriedEnumResource::class, 'toArray', null, 'tests/fixtures');

        expect($result->typeImports)->toBe(['../../workbench/app/enums' => ['StatusType']]);
    });

    it('imports each carried class an event cast\'s text spells', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CastCarriedSpelledEvent::class]);

        expect($transformer->typeImports)->toBe([
            '../../../../workbench/app/enums' => ['StatusType'],
            '../../../../workbench/app/http/resources' => ['TeamResource'],
            '../../../../workbench/app/models' => ['User'],
        ]);
    });

    it('forces no alias on an event\'s class of the same name', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CastCarriedModelEvent::class]);

        expect(array_column($transformer->properties, 'type'))->toBe(['ReviewerCard | null', 'Partial<User>'])
            ->and($transformer->typeImports)->toBe([
                '@js/types/reviews' => ['ReviewerCard'],
                '../../../../workbench/crm/models' => ['User'],
            ]);
    });
});

// A cast's import binds each name it brings, so the same name from any other path would collide (TS2300).
describe('a cast\'s import and another import of its name', function () {
    it('keeps only the class-level cast\'s import over the broadcastWith() cast it overrides', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => OverriddenMethodCastImportEvent::class]);

        expect(array_column($transformer->properties, 'type'))->toBe(['ReviewerCard | null'])
            ->and($transformer->typeImports)->toBe(['@js/types/reviews' => ['ReviewerCard']]);
    });

    it('keeps an event attribute\'s #[TsType] import beside a cast on a key no payload property names', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => NonPayloadCastImportEvent::class]);

        expect(array_column($transformer->properties, 'type'))->toBe(['MenuSettingsType | null'])
            ->and($transformer->typeImports)->toBe(['@js/types/settings' => ['MenuSettingsType']]);
    });

    it('drops an event attribute\'s #[TsType] import of the name the cast brings', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CastOverAttributeImportEvent::class]);

        expect(array_column($transformer->properties, 'type'))->toBe(['MenuSettingsType | null'])
            ->and($transformer->typeImports)->toBe(['@js/types/menu' => ['MenuSettingsType']]);
    });

    it('drops an attribute\'s #[TsType] import of the name a method cast brings, through AstEngine::analyze()', function () {
        $result = resolve(AstEngine::class)->analyze(MethodCastOverAttributeImportResource::class, 'toArray', null, 'tests/fixtures');

        expect(array_column($result->properties, 'type', 'name'))->toBe(['menu_config' => 'MenuSettingsType | null'])
            ->and($result->typeImports)->toBe(['@js/types/menu' => ['MenuSettingsType']]);
    });
});

describe('a cast the resource and its model both declare', function () {
    it('lets a resource\'s own toArray() cast win over its model\'s cast', function () {
        expect(castInForceLines(ModelVsMethodCastResource::class))->toContain('    longitude: MethodLongitude;');
    });

    it('keeps the model cast\'s optional flag under a resource cast that says nothing about it', function (string $resource, string $line) {
        expect(castInForceLines($resource))->toContain($line);
    })->with([
        'a type-only toArray() cast' => [ModelOptionalVsMethodCastResource::class, '    latitude?: MethodLatitude;'],
        'a toArray() cast with optional => false' => [ModelOptionalVsRequiredMethodCastResource::class, '    latitude: MethodLatitude;'],
        'a type-only class cast' => [ModelOptionalVsClassCastResource::class, '    latitude?: ClassLatitude;'],
    ]);

    // Both branches cast the key to one type, so the union publishes that cast over the model's, whatever each flag.
    it('lets two return branches\' casts of one type win over the model\'s cast when only one marks it optional', function () {
        expect(castInForceLines(BranchCastsOptionalApartResource::class))->toContain('    longitude?: BranchLongitude;');
    });
});

// #81: a model that no key reads any more is neither imported nor a reason to alias the one a key still reads.
describe('two same-basename models, one of them displaced', function () {
    it('imports only the model a key still reads, under its own name', function (string $resource, array $lines) {
        expect(castInForceLines($resource))->toBe($lines);
    })->with([
        'a class-level cast' => [
            SameBasenameOverrideResource::class,
            [
                "import type { User } from '../../../../workbench/app/models';",
                '    manager: User | null;',
                '    contact: { id: number } | null;',
            ],
        ],
        'a method-level cast' => [
            MethodSameBasenameOverrideResource::class,
            [
                "import type { User } from '../../../../workbench/app/models';",
                '    manager: User | null;',
                '    contact: { id: number } | null;',
            ],
        ],
        'a key that overrides a spread helper\'s key' => [
            RewrittenSpreadKeyResource::class,
            [
                "import type { User } from '../../../../workbench/app/models';",
                '    who: string;',
                '    manager: User | null;',
            ],
        ],
    ]);
});

describe('CastChannels', function () {
    it('keeps a class its key\'s cast spells as an embedded name, and carries one it does not', function () {
        $analysis = new MethodAnalysis(
            properties: [['name' => 'k', 'type' => 'StatusType', 'optional' => false, 'description' => '']],
            enumResources: ['k' => Status::class],
            multiEnumResourceFqcns: ['k' => [Status::class, Visibility::class]],
        );

        resolve(CastChannels::class)->fit($analysis, ['k' => ['type' => 'StatusType | null', 'import' => null]]);

        expect($analysis->enumResources)->toBe([])
            ->and($analysis->multiEnumResourceFqcns)->toBe([])
            ->and($analysis->inlineEnumFqcns)->toBe(['k' => [Status::class]])
            ->and($analysis->directEnumFqcns)->toBe([Status::class => Status::class])
            ->and($analysis->carried)->toBe(['enums' => [Visibility::class]])
            ->and($analysis->inlineEnumResourceFqcns)->toBe(['k' => [Status::class, Visibility::class]]);
    });

    it('drops a class whose name the cast\'s import brings, unless another key reads it', function () {
        $analysis = new MethodAnalysis(
            properties: [
                ['name' => 'k', 'type' => 'User | null', 'optional' => false, 'description' => ''],
                ['name' => 'crm', 'type' => 'User', 'optional' => false, 'description' => ''],
            ],
            modelFqcns: ['k' => User::class, 'crm' => CrmUser::class],
            inlineModelFqcns: ['k' => [CrmUser::class]],
            casts: ['k' => ['type' => 'User | null', 'import' => '@js/types/user']],
        );

        resolve(CastChannels::class)->fit($analysis, ['k' => ['type' => 'User | null', 'import' => '@js/types/user']]);

        expect($analysis->modelFqcns)->toBe(['crm' => CrmUser::class])
            ->and($analysis->inlineModelFqcns)->toBe([])
            ->and($analysis->carried)->toBe([])
            ->and($analysis->casts)->toBe(['k' => ['type' => 'User | null', 'import' => '@js/types/user']]);
    });

    it('leaves a key no cast retypes, and a cast key with no channel, as they are', function () {
        $analysis = new MethodAnalysis(
            properties: [['name' => 'a', 'type' => 'PriorityType', 'optional' => false, 'description' => '']],
            directEnumFqcns: ['a' => Priority::class],
        );

        resolve(CastChannels::class)->fit($analysis, ['b' => ['type' => 'PriorityType', 'import' => null]]);

        expect($analysis->directEnumFqcns)->toBe(['a' => Priority::class]);
    });

    it('drops a class one key carries when another key\'s import brings its name', function () {
        $analysis = new MethodAnalysis(
            properties: [
                ['name' => 'a', 'type' => 'string', 'optional' => false, 'description' => ''],
                ['name' => 'b', 'type' => 'StatusType', 'optional' => false, 'description' => ''],
            ],
            directEnumFqcns: ['a' => Status::class, 'b' => Status::class],
        );

        resolve(CastChannels::class)->fit($analysis, [
            'a' => ['type' => 'string', 'import' => null],
            'b' => ['type' => 'StatusType', 'import' => '@js/types/status'],
        ]);

        expect($analysis->directEnumFqcns)->toBe([])
            ->and($analysis->carried)->toBe([]);
    });

    it('drops another path\'s import of a name a cast\'s import brings, and keeps the cast\'s own', function () {
        $analysis = new MethodAnalysis(
            properties: [['name' => 'k', 'type' => 'MenuSettingsType', 'optional' => false, 'description' => '']],
            customImports: ['@js/types/settings' => ['MenuSettingsType', 'Other'], '@js/types/menu' => ['MenuSettingsType']],
        );

        resolve(CastChannels::class)->fit($analysis, ['k' => ['type' => 'MenuSettingsType', 'import' => '@js/types/menu']]);

        expect($analysis->customImports)->toBe(['@js/types/settings' => ['Other'], '@js/types/menu' => ['MenuSettingsType']]);
    });

    it('fits a numeric cast key, which PHP stores as an int', function () {
        $analysis = new MethodAnalysis(
            properties: [['name' => '6', 'type' => 'StatusType', 'optional' => false, 'description' => '']],
            directEnumFqcns: ['6' => Status::class],
        );

        resolve(CastChannels::class)->fit($analysis, [6 => ['type' => 'string', 'import' => null]]);

        expect($analysis->directEnumFqcns)->toBe([])
            ->and($analysis->carried)->toBe(['enums' => [Status::class]]);
    });
});
