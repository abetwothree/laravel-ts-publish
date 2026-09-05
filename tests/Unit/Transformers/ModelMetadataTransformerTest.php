<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AstUnimportableModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\BranchedAstModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CircularJsonSerializableMetadataValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ConfigurableModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InvalidMetadataPayloadProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InvalidModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\JsonSerializableMetadataValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MismatchedModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MissingRequiredMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\OptionalModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnimportableMetadataTypeProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnsupportedMetadataValue;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Address;
use Workbench\App\Models\User;
use Workbench\App\Providers\AstInferredModelMetadataProvider;

test('infers default metadata types from the provider return shape', function () {
    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->properties)->toBe(['morphClass' => User::class])
        ->and($data->propertyTypes)->toBe(['morphClass' => 'string'])
        ->and($data->typeImports)->toBe([])
        ->and($data->filename)->toBe('user_meta');
});

test('falls back to body inference for a provider with a generic array declaration', function () {
    config()->set('ts-publish.model_metadata.provider_class', AstInferredModelMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->properties)->toBe([
        'morphClass' => User::class,
        'enabled' => true,
        'limits' => [
            'minimum' => 1,
            'maximum' => null,
        ],
        'role' => 'Admin',
    ])->and($data->propertyTypes)->toBe([
        'morphClass' => 'string',
        'enabled' => 'boolean',
        'limits' => '{ minimum: number; maximum: null }',
        'role' => 'RoleType',
    ])->and($data->typeImports)->toBe([
        '../enums' => ['RoleType'],
    ]);
});

test('does not accept named body values without an import-aware TsCasts declaration', function () {
    config()->set('ts-publish.model_metadata.provider_class', AstUnimportableModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'cannot infer an import; declare it with #[TsCasts]');
});

test('uses only body-inferred keys present in the concrete model payload', function () {
    config()->set('ts-publish.model_metadata.provider_class', BranchedAstModelMetadataProvider::class);

    $user = (new ModelMetadataTransformer(User::class))->data();
    $address = (new ModelMetadataTransformer(Address::class))->data();

    expect($user->properties)->toBe(['userModel' => true])
        ->and($user->propertyTypes)->toBe(['userModel' => 'boolean'])
        ->and($address->properties)->toBe(['otherModel' => 1])
        ->and($address->propertyTypes)->toBe(['otherModel' => 'number']);
});

test('transforms configured morph map aliases', function () {
    $previousMorphMap = Relation::morphMap();
    Relation::morphMap(['frontend-user' => User::class], false);

    try {
        $data = (new ModelMetadataTransformer(User::class))->data();

        expect($data->properties)->toBe(['morphClass' => 'frontend-user']);
    } finally {
        Relation::morphMap($previousMorphMap, false);
    }
});

test('fails for an unmapped model when morph maps are required', function () {
    $previousMorphMap = Relation::morphMap();
    $previousRequirement = Relation::requiresMorphMap();
    Relation::enforceMorphMap(['user' => User::class], false);

    try {
        expect(fn () => new ModelMetadataTransformer(Address::class))
            ->toThrow(ClassMorphViolationException::class);
    } finally {
        Relation::morphMap($previousMorphMap, false);
        Relation::requireMorphMap($previousRequirement);
    }
});

test('transforms metadata with a custom provider and TsCasts types', function () {
    config()->set('ts-publish.model_metadata.provider_class', CustomModelMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->properties)->toBe([
        'table' => 'users',
        'details' => ['exists' => false],
    ])->and($data->propertyTypes)->toBe([
        'table' => 'string',
        'details' => 'ModelMetadataDetails',
    ])->and($data->typeImports)->toBe([
        '@/types/model-metadata' => ['ModelMetadataDetails'],
    ]);
});

test('uses optional return-shape keys only to validate the concrete payload', function () {
    config()->set('ts-publish.model_metadata.provider_class', OptionalModelMetadataProvider::class);

    $withOptionalValue = (new ModelMetadataTransformer(User::class))->data();
    $withoutOptionalValue = (new ModelMetadataTransformer(Address::class))->data();

    expect($withOptionalValue->properties)->toBe(['table' => 'users', 'exists' => false])
        ->and($withOptionalValue->propertyTypes)->toBe([
            'table' => 'string',
            'exists' => 'ExistsFlag',
        ])->and($withOptionalValue->typeImports)->toBe([
            '@/types/exists-flag' => ['ExistsFlag'],
        ])->and($withoutOptionalValue->properties)->toBe(['table' => 'addresses'])
        ->and($withoutOptionalValue->propertyTypes)->toBe([
            'table' => 'string',
        ])->and($withoutOptionalValue->typeImports)->toBe([]);
});

test('rejects a configured class that does not implement the metadata provider contract', function () {
    config()->set('ts-publish.model_metadata.provider_class', InvalidModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'must implement');
});

test('rejects metadata payloads without string property keys', function () {
    config()->set('ts-publish.model_metadata.provider_class', InvalidMetadataPayloadProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'must use string keys');
});

test('rejects returned metadata keys without inferred or declared types', function () {
    config()->set('ts-publish.model_metadata.provider_class', MismatchedModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'model [Workbench\App\Models\User] returned keys without inferred or declared types: [table]');
});

test('requires every non-optional return-shape key to have a value', function () {
    config()->set('ts-publish.model_metadata.provider_class', MissingRequiredMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'model [Workbench\App\Models\User] is missing required keys: [exists]');
});

test('resolves the model instance through the container', function () {
    $model = new User;
    $model->setTable('container_users');
    app()->instance(User::class, $model);
    config()->set('ts-publish.model_metadata.provider_class', CustomModelMetadataProvider::class);

    expect((new ModelMetadataTransformer(User::class))->data()->properties['table'])
        ->toBe('container_users');
});

test('requires TsCasts for inferred types whose imports cannot be inferred', function () {
    config()->set('ts-publish.model_metadata.provider_class', UnimportableMetadataTypeProvider::class);

    expect((new ModelMetadataTransformer(Address::class))->data()->properties)->toBe(['table' => 'addresses']);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'cannot infer an import; declare it with #[TsCasts]');
});

test('normalizes supported nested metadata values', function () {
    $provider = new ConfigurableModelMetadataProvider([
        'null' => null,
        'boolean' => true,
        'integer' => 42,
        'float' => 3.14,
        'string' => 'metadata',
        'backedEnum' => Status::Published,
        'unitEnum' => Role::Admin,
        'arrayable' => collect(['nested' => Status::Draft]),
        'jsonSerializable' => new JsonSerializableMetadataValue(['nested' => Role::Guest]),
    ]);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect((new ModelMetadataTransformer(User::class))->data()->properties['value'])->toBe([
        'null' => null,
        'boolean' => true,
        'integer' => 42,
        'float' => 3.14,
        'string' => 'metadata',
        'backedEnum' => 1,
        'unitEnum' => 'Admin',
        'arrayable' => ['nested' => 0],
        'jsonSerializable' => ['nested' => 'Guest'],
    ]);
});

test('rejects unsupported metadata values with the model and nested property path', function () {
    $provider = new ConfigurableModelMetadataProvider(['nested' => new UnsupportedMetadataValue]);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(
            InvalidArgumentException::class,
            'model [Workbench\\App\\Models\\User] property [value.nested] returned unsupported value [AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\UnsupportedMetadataValue]',
        );
});

test('rejects non-finite metadata floats with the model and nested property path', function () {
    $provider = new ConfigurableModelMetadataProvider(['nested' => INF]);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(
            InvalidArgumentException::class,
            'model [Workbench\\App\\Models\\User] property [value.nested] returned a non-finite float',
        );
});

test('rejects circular serializable metadata values', function () {
    $provider = new ConfigurableModelMetadataProvider(new CircularJsonSerializableMetadataValue);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(
            InvalidArgumentException::class,
            'model [Workbench\\App\\Models\\User] property [value] contains a circular object value',
        );
});

test('rejects metadata values exceeding the maximum nesting depth', function () {
    $value = 'leaf';

    for ($depth = 0; $depth < 66; $depth++) {
        $value = [$value];
    }

    $provider = new ConfigurableModelMetadataProvider($value);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'exceeds the maximum nesting depth of 64');
});
