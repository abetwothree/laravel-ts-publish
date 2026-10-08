<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CallPathFactory;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CallPathModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CallPathModelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CallPathSubjectResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CallPathWrappedResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A call-path fixture's published property types, keyed by property name.
 *
 * @param  class-string  $resource
 * @param  class-string|null  $model
 * @return array<string, string>
 */
function classHeldTypes(string $resource, ?string $model): array
{
    return collect(new ResourceAstAnalyzer(new ReflectionClass($resource), $model)->analyze()->properties)
        ->mapWithKeys(fn (array $property): array => [$property['name'] => $property['type']])
        ->all();
}

afterEach(function () {
    PublishedModelRegistry::reset();
    resolve(AnalysisMemo::class)->reset();
});

describe('MethodReturnTypeResolver::resolve()', function () {
    test('declines a framework or abstract model, which no generated file exports', function () {
        $resolver = resolve(MethodReturnTypeResolver::class);

        expect($resolver->resolve(CallPathFactory::class, 'baseModel'))->toBeNull()
            ->and($resolver->resolve(CallPathFactory::class, 'authUser'))->toBeNull()
            ->and($resolver->resolve(CallPathFactory::class, 'abstractModel'))->toBeNull()
            ->and($resolver->resolve(CallPathFactory::class, 'post'))
            ->toBe(['type' => 'Post', 'optional' => false, 'modelFqcn' => Post::class]);
    });

    test('declines a model the run does not publish', function () {
        PublishedModelRegistry::register([User::class]);

        expect(resolve(MethodReturnTypeResolver::class)->resolve(CallPathFactory::class, 'post'))->toBeNull();
    });
});

describe('every call path that holds a class names only published models', function () {
    test('types a static call, self::, $this:: and an own method as json_encode() writes them', function () {
        expect(classHeldTypes(CallPathSubjectResource::class, null))->toBe([
            'static_plain_date' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
            'static_interval' => LaravelTsPublishService::CARBON_INTERVAL_OBJECT_TYPE,
            'static_nullable_text' => 'string | null',
            'static_carbon' => 'string',
            'static_base_model' => 'unknown',
            'static_auth_user' => 'unknown',
            'static_abstract_model' => 'unknown',
            'static_post' => 'Post',
            'static_arm' => 'Post',
            'static_date_arm' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE.' | null',
            'self_base_model' => 'unknown',
            'this_static_base_model' => 'unknown',
            'own_base_model' => 'unknown',
            'own_auth_user' => 'unknown',
        ]);
    });

    test('a wrapped class and a backing model: $this->resource::m(), a forwarded $this->m() and $this->resource->m()', function () {
        expect(classHeldTypes(CallPathWrappedResource::class, null))->toBe([
            'resource_static_label' => 'string',
            'resource_static_plain_date' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
            'resource_static_base_model' => 'unknown',
            'forwarded_label' => 'string',
            'forwarded_plain_date' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
            'forwarded_base_model' => 'unknown',
            'forwarded_auth_user' => 'unknown',
            'chained_plain_date' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
            'chained_base_model' => 'unknown',
        ])->and(classHeldTypes(CallPathModelResource::class, CallPathModel::class))->toBe([
            'resource_static_label' => 'string',
            'resource_static_plain_date' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
            'resource_static_base_model' => 'unknown',
            'forwarded_label' => 'string',
            'forwarded_plain_date' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
            'forwarded_base_model' => 'unknown',
            'forwarded_auth_user' => 'unknown',
            'chained_plain_date' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
            'chained_base_model' => 'unknown',
            'relation_nullsafe_base_model' => 'unknown',
            'relation_static_base_model' => 'unknown',
        ]);
    });
});
