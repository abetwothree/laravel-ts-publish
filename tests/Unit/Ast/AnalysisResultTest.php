<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisComposer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisResult;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use Workbench\App\Enums\Role;
use Workbench\App\Events\PayloadDiffersEvent;
use Workbench\App\Http\Resources\ApiPostResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\ImageDelegatedResource;
use Workbench\App\Http\Resources\TernaryResource;
use Workbench\App\Http\Resources\ToArrayCastsResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

describe('AstEngine::analyze()', function () {
    test('wraps an EnumResource property, so the value import it emits is the one the type reads', function () {
        // buildValueImports() short-circuits to [] when this is off, which would make the value-import
        // assertion vacuous rather than a statement about the wrap.
        config()->set('ts-publish.enums.use_tolki_package', true);

        $result = resolve(AstEngine::class)->analyze(UserResource::class, 'toArray', null, 'workbench/app/http/resources');

        expect($result)->toBeInstanceOf(AnalysisResult::class)
            ->and(collect($result->properties)->firstWhere('name', 'role')['type'])->toBe('AsEnum<typeof Role> | null')
            ->and($result->valueImports)->toBe(['../../enums' => ['Role']])
            ->and($result->typeImports)->toBe([
                '../../models' => ['Profile'],
                '.' => ['PostResource'],
            ]);
    });

    test('leaves the bare enum type and its type import when the tolki package is off', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $result = resolve(AstEngine::class)->analyze(UserResource::class, 'toArray', null, 'workbench/app/http/resources');

        expect(collect($result->properties)->firstWhere('name', 'role')['type'])->toBe('RoleType | null')
            ->and($result->typeImports['../../enums'] ?? null)->toBe(['RoleType'])
            ->and($result->valueImports)->toBe([]);
    });

    test('aliases two same-basename models apart in the types as well as the imports', function () {
        $result = resolve(AstEngine::class)
            ->analyze(ImageDelegatedResource::class, 'toArray', null, 'workbench/app/http/resources');

        expect(collect($result->properties)->firstWhere('name', 'reviewable')['type'])
            ->toBe('CrmUser | WorkbenchUser | null')
            ->and($result->typeImports)->toBe([
                '@js/types/settings' => ['MenuSettingsType'],
                '../../../crm/models' => ['User as CrmUser'],
                '../../enums' => ['SizeType', 'StatusType'],
                '../../models' => ['User as WorkbenchUser'],
            ]);
    });

    test('names both arms of a mixed ternary, and each branch of a multi-enum one', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);

        $properties = collect(
            resolve(AstEngine::class)->analyze(TernaryResource::class, 'toArray', null, 'workbench/app/http/resources')
                ->properties
        )->keyBy('name');

        expect($properties['status_resource_or_type']['type'])->toBe('AsEnum<typeof Status> | StatusType')
            ->and($properties['status_or_visibility']['type'])
            ->toBe('AsEnum<typeof Status> | AsEnum<typeof Visibility> | null');
    });

    test('drops the value import of an enum whose wrap no longer survives in the type', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);

        // A method-level #[TsCasts] retypes the key the analyzer recorded as an EnumResource wrap, so
        // the substitution finds no bare token and the const it would have bound goes unspelled.
        $analysis = new MethodAnalysis(
            properties: [['name' => 'role', 'type' => 'string', 'optional' => false, 'description' => '']],
            enumResources: ['role' => Role::class],
        );

        expect(new AnalysisComposer()->compose($analysis, 'workbench/app/http/resources')->valueImports)->toBe([]);
    });

    test('imports nothing a property type no longer names', function () {
        // ToArrayCastsResource's method-level #[TsCasts] retypes `role` as a plain string, but the
        // enum FQCN stays on the analysis — importing RoleType would be a dead import.
        $result = resolve(AstEngine::class)
            ->analyze(ToArrayCastsResource::class, 'toArray', null, 'workbench/app/http/resources');

        expect(collect($result->properties)->firstWhere('name', 'role')['type'])->toBe('string')
            ->and($result->typeImports)->toBe(['@/types/geo' => ['GeoPoint']]);
    });

    test('collapses a property name two branches both set into one member', function () {
        $engine = resolve(AstEngine::class);
        $names = array_column(
            $engine->analyze(ApiPostResource::class, 'toArray', null, 'workbench/app/http/resources')->properties,
            'name',
        );

        // The raw analysis appends, so the same three names arrive twice; rendered as-is they are
        // duplicate interface members.
        expect(array_column($engine->analyzeMethod(ApiPostResource::class)->properties, 'name'))
            ->toContain('status', 'visibility', 'priority')
            ->and(count($engine->analyzeMethod(ApiPostResource::class)->properties))->toBe(count($names) + 3)
            ->and($names)->toBe(array_values(array_unique($names)));
    });

    test('a resource that reads an enum directly carries a type import and no value import', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);

        $result = resolve(AstEngine::class)->analyze(CommentResource::class, 'toArray', null, 'workbench/app/http/resources');

        expect($result->typeImports)->toHaveKey('../../enums')
            ->and($result->typeImports['../../enums'])->toContain('StatusType')
            ->and($result->valueImports)->toBe([]);
    });

    test('forwards the method and model class to analyzeMethod()', function () {
        $engine = resolve(AstEngine::class);

        expect(array_column($engine->analyze(PayloadDiffersEvent::class, 'broadcastWith')->properties, 'name'))
            ->toBe(['team', 'kind', 'count'])
            ->and($engine->analyze(PayloadDiffersEvent::class)->properties)->toBe([]);

        $resolved = $engine->analyze(UserResource::class, 'toArray', null, 'workbench/app/http/resources');
        $overridden = $engine->analyze(UserResource::class, 'toArray', Post::class, 'workbench/app/http/resources');

        expect(collect($resolved->properties)->firstWhere('name', 'name')['type'])->toBe('string')
            ->and(collect($overridden->properties)->firstWhere('name', 'name')['type'])->toBe('unknown');
    });
});
