<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastOneOfTwoWrapsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CastWrapTypeImportResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastMixedTernaryResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MethodCastWrapTypeImportResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ModelVsMethodCastResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SiblingSpreadCastsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SiblingSpreadCastsReversedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SpreadOverSpreadEnumResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\PostSpelledCastResource;
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

    return array_values(preg_grep('/^(import |    [a-z_]+\??: )/', explode("\n", $content)) ?: []);
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

// #111: a spread helper's cast is final for the key it publishes, and the last spread's cast is the one in force.
describe('a cast a spread helper declares', function () {
    it('publishes each spread cast as written, aliasing only the package\'s own names', function (string $resource, array $lines) {
        expect(castInForceLines($resource))->toBe($lines);
    })->with([
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
});

describe('a cast the resource and its model both declare', function () {
    it('lets a resource\'s own toArray() cast win over its model\'s cast', function () {
        expect(castInForceLines(ModelVsMethodCastResource::class))->toContain('    longitude: MethodLongitude;');
    });
});

describe('CastChannels', function () {
    it('keeps a class its key\'s cast spells as an embedded name, and carries one it does not', function () {
        $analysis = new MethodAnalysis(
            properties: [['name' => 'k', 'type' => 'StatusType', 'optional' => false, 'description' => '']],
            enumResources: ['k' => Status::class],
            multiEnumResourceFqcns: ['k' => [Status::class, Visibility::class]],
        );

        resolve(CastChannels::class)->fit($analysis, ['k' => ['type' => 'StatusType | null', 'import' => false]]);

        expect($analysis->enumResources)->toBe([])
            ->and($analysis->multiEnumResourceFqcns)->toBe([])
            ->and($analysis->inlineEnumFqcns)->toBe(['k' => [Status::class]])
            ->and($analysis->directEnumFqcns)->toBe([Status::class => Status::class, Visibility::class => Visibility::class])
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
            casts: ['k' => ['type' => 'User | null', 'import' => true]],
        );

        resolve(CastChannels::class)->fit($analysis, ['k' => ['type' => 'User | null', 'import' => true]]);

        expect($analysis->modelFqcns)->toBe(['crm' => CrmUser::class])
            ->and($analysis->inlineModelFqcns)->toBe([])
            ->and($analysis->casts)->toBe(['k' => ['type' => 'User | null', 'import' => true]]);
    });

    it('leaves a key no cast retypes, and a cast key with no channel, as they are', function () {
        $analysis = new MethodAnalysis(
            properties: [['name' => 'a', 'type' => 'PriorityType', 'optional' => false, 'description' => '']],
            directEnumFqcns: ['a' => Priority::class],
        );

        resolve(CastChannels::class)->fit($analysis, ['b' => ['type' => 'PriorityType', 'import' => false]]);

        expect($analysis->directEnumFqcns)->toBe(['a' => Priority::class]);
    });
});
