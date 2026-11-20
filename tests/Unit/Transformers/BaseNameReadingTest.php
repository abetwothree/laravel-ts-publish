<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\ClassTypeOverride;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\CastBuildsOnModelEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events\NestedNameExtendsEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\BuildsOnModelCastController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Requests\NestedNameCastRequest;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CastBuildsOnResourceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MethodCastBuildsOnResourceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ModelCastBuildsOnSiblingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NestedNameCastSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadHelperCastBuildsOnSiblingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NestedNameCastPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\NestedNameCastMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithNestedNameCast;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Illuminate\Routing\Router;
use Workbench\App\Models\User;

// Every cast, and every clause off a resource, reads its text as extractImportableTypes() does, its top-level arms, so
// a name it only builds on imports from no path it did not before: each case pins base's imports.
describe('a cast, or a clause off a resource, with an import that only builds on a name', function () {
    it('keeps a resource\'s imports for its class, method, model or spread helper\'s cast', function (string $resource, array $types) {
        $transformer = new ResourceTransformer($resource);

        expect(array_column($transformer->properties, 'type'))->toBe($types)
            ->and($transformer->typeImports)->toBe(['../../../../../../workbench/app/http/resources' => ['UserResource']]);
    })->with([
        'a class cast' => [CastBuildsOnResourceResource::class, ['{ owner: UserResource; meta: Meta }', 'Paginated<UserResource>']],
        'a method cast' => [MethodCastBuildsOnResourceResource::class, ['{ owner: UserResource; meta: Meta }', 'Paginated<UserResource>']],
        'a model cast' => [ModelCastBuildsOnSiblingResource::class, ['Paginated<UserResource>', 'UserResource']],
        'a spread helper\'s cast' => [SpreadHelperCastBuildsOnSiblingResource::class, ['Paginated<UserResource>', 'UserResource']],
    ]);

    it('keeps an event\'s imports for its class cast', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => CastBuildsOnModelEvent::class]);

        expect(array_column($transformer->properties, 'type'))->toBe(['{ owner: User; meta: Meta }'])
            ->and($transformer->typeImports)->toBe(['../../../../../workbench/app/models' => ['User']]);
    });

    it('keeps a page\'s imports for its action cast', function () {
        app(Router::class)->get('/builds-on-model-cast/{post}', [BuildsOnModelCastController::class, 'show']);
        $transformer = new RouteTransformer(BuildsOnModelCastController::class);

        expect($transformer->actions[0]['pageType'])->toBe('Inertia.SharedData & { meta: { author: User | null; tag: Tag } }')
            ->and($transformer->typeImports)->toBe(['../../../../../../workbench/app/models' => ['User']]);
    });

    // Pins base's known defect: shared data and a companion import a cast's whole text as one name (a follow-up issue).
    it('keeps shared data\'s imports', function () {
        $result = analyzeSharedDataFor(MiddlewareWithNestedNameCast::class);

        expect($result['typeImports'] ?? null)->toBe([
            './workbench/app/models' => ['User'],
            '@/types/meta' => ['{ user: User | null; meta: Meta }'],
        ]);
    });

    // Pins base's known defect too: the whole cast text imported as one name, slated for the same follow-up issue.
    it('keeps a model metadata companion\'s imports', function () {
        config()->set('ts-publish.model_metadata.provider_class', NestedNameCastMetadataProvider::class);

        expect((new ModelMetadataTransformer(User::class))->data()->typeImports)->toBe([
            '@/types/meta' => ['{ role: RoleType; meta: Meta }'],
        ]);
    });

    it('keeps a model interface\'s imports for its own cast and extends clause', function () {
        $transformer = new ModelTransformer(NestedNameCastPost::class);

        expect($transformer->customImports)->toBe([])
            ->and(array_keys($transformer->data()->typeImports))->toBe([
                '../../../../../workbench/app/enums',
                '../../../../../workbench/app/models',
            ]);
    });

    it('keeps a spread model\'s column cast imports', function () {
        $transformer = new ResourceTransformer(NestedNameCastSpreadResource::class);

        expect($transformer->properties['title']['type'])->toBe('Paginated<UserResource>')
            ->and(array_keys($transformer->typeImports))->toBe(['../../../../../../workbench/app/enums']);
    });

    it('keeps a form request\'s imports for its cast and extends clause', function () {
        expect((new FormRequestTransformer(NestedNameCastRequest::class))->typeImports)->toBe([]);
    });

    it('keeps an event\'s imports for its extends clause', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => NestedNameExtendsEvent::class]);

        expect($transformer->typeImports)->toBe(['../../../../../workbench/app/models' => ['User']]);
    });

    it('keeps a `#[TsType]` override\'s imports', function () {
        expect(ClassTypeOverride::importsOf([
            'type' => 'Paginated<UserResource>',
            'import' => '@/types/paginated',
            'declaredBy' => User::class,
        ]))->toBe([]);
    });
});
