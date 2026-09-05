<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AliasedCastsAndInferredEnumMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AstEmptyValuesModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AstUnimportableModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\BoundModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\BranchedAstModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CircularJsonSerializableMetadataValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ConfigurableModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CustomModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EmptyValuesModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\FreshObjectJsonSerializableMetadataValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InheritedModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InvalidMetadataPayloadProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InvalidModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\JsonSerializableMetadataValue;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MismatchedModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MissingRequiredMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\OptionalModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TupleShapeMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnimportableMetadataTypeProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnsafeIntegerBackedStatus;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\UnsafeIntegerMetadataProvider;
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

test('imports the enum a body-inferred value names', function () {
    config()->set('ts-publish.model_metadata.provider_class', AstUnimportableModelMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->propertyTypes)->toBe(['role' => 'RoleType'])
        ->and($data->typeImports)->toBe(['../enums' => ['RoleType']])
        ->and($data->properties)->toBe(['role' => 'Admin']);
});

test('does not import a model for a model-typed value', function () {
    $provider = new ConfigurableModelMetadataProvider(new User);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect((new ModelMetadataTransformer(User::class))->data()->propertyTypes)->toBe(['value' => 'unknown'])
        ->and(resolve(ModelMetadataAnalyzer::class)
            ->analyze(BoundModelMetadataProvider::class, ['table'], 'workbench/app/models')->typeImports)
        ->toBe([]);
});

test('aliases two same-named cast imports beside the unaliased name inference claims', function () {
    config()->set('ts-publish.model_metadata.provider_class', AliasedCastsAndInferredEnumMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->propertyTypes)->toBe([
        'first' => 'FirstRoleType',
        'second' => 'SecondRoleType',
        'role' => 'RoleType',
    ])->and($data->typeImports)->toBe([
        '../enums' => ['RoleType'],
        '@/types/first' => ['RoleType as FirstRoleType'],
        '@/types/second' => ['RoleType as SecondRoleType'],
    ]);
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

test('names companions with a suffix no model interface filename can carry', function () {
    expect(ModelMetadataTransformer::filenameFor(User::class))->toBe('user_meta')
        ->and(ModelMetadataTransformer::filenameFor('Workbench\\App\\Models\\PostMeta'))->toBe('post-meta_meta')
        ->and(ModelMetadataTransformer::isMetadataFilename('user_meta'))->toBeTrue()
        ->and(ModelMetadataTransformer::isMetadataFilename('post-meta'))->toBeFalse()
        ->and((new ModelMetadataTransformer(User::class))->filename())->toBe('user_meta');
});

test('infers string types for bound model method calls under a generic array declaration', function () {
    config()->set('ts-publish.model_metadata.provider_class', BoundModelMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->propertyTypes)->toBe([
        'table' => 'string',
        'keyName' => 'string',
        'routeKeyName' => 'string',
        'morphClass' => 'string',
    ])->and($data->properties['table'])->toBe('users')
        ->and($data->typeImports)->toBe([]);
});

test('an inherited provide() body infers with the model parameter bound', function () {
    config()->set('ts-publish.model_metadata.provider_class', InheritedModelMetadataProvider::class);

    expect((new ModelMetadataTransformer(User::class))->data()->propertyTypes)
        ->toBe(['enabled' => 'boolean', 'table' => 'string']);
});

test('spells an empty PHP array as an empty object wherever its type is object-like', function () {
    config()->set('ts-publish.model_metadata.provider_class', EmptyValuesModelMetadataProvider::class);

    $properties = (new ModelMetadataTransformer(User::class))->data()->properties;

    expect($properties['flags'])->toBeInstanceOf(stdClass::class)
        ->and($properties['tags'])->toBe([])
        ->and($properties['nested']['items'])->toBeInstanceOf(stdClass::class)
        ->and($properties['nested']['ids'])->toBe([])
        ->and($properties['opaque'])->toBe([])
        ->and($properties['explicit'])->toBeInstanceOf(stdClass::class)
        ->and($properties['maybe'])->toBe([]);
});

test('spells body-inferred empty containers by their inferred type', function () {
    config()->set('ts-publish.model_metadata.provider_class', AstEmptyValuesModelMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    // A literal [] infers never[] (InlineArrayHandler); a helper's array<string, bool> infers Record<string, boolean>.
    expect($data->propertyTypes['empty'])->toBe('never[]')
        ->and($data->propertyTypes['nested'])->toContain('items: never[]')
        ->and($data->propertyTypes['flags'])->toBe('Record<string, boolean>')
        ->and($data->properties['empty'])->toBe([])
        ->and($data->properties['nested'])->toBe(['items' => []])
        ->and($data->properties['flags'])->toBeInstanceOf(stdClass::class);
});

test('normalizes a non-empty stdClass like an associative array', function () {
    $provider = new ConfigurableModelMetadataProvider((object) ['nested' => (object) ['flag' => Status::Published]]);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect((new ModelMetadataTransformer(User::class))->data()->properties['value'])
        ->toBe(['nested' => ['flag' => 1]]);
});

test('rejects integers outside the JavaScript safe range with the property path', function () {
    config()->set('ts-publish.model_metadata.provider_class', UnsafeIntegerMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(
            InvalidArgumentException::class,
            'property [snowflake] exceeds JavaScript\'s safe integer range (±9007199254740991); return it as a string.',
        );
});

test('rejects NAN like any other non-finite float', function () {
    $provider = new ConfigurableModelMetadataProvider(['nested' => NAN]);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'property [value.nested] returned a non-finite float');
});

test('counts nesting depth per array level and treats objects as transparent wrappers', function () {
    $nest = function (int $levels, bool $wrapInObjects): mixed {
        $value = 'leaf';

        for ($level = 0; $level < $levels; $level++) {
            $value = $wrapInObjects ? new JsonSerializableMetadataValue([$value]) : [$value];
        }

        return $value;
    };

    foreach ([false, true] as $wrapInObjects) {
        $provider = new ConfigurableModelMetadataProvider($nest(64, $wrapInObjects));
        app()->instance(ConfigurableModelMetadataProvider::class, $provider);
        config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

        expect((new ModelMetadataTransformer(User::class))->data()->properties['value'])->toBeArray();

        $provider = new ConfigurableModelMetadataProvider($nest(65, $wrapInObjects));
        app()->instance(ConfigurableModelMetadataProvider::class, $provider);

        expect(fn () => new ModelMetadataTransformer(User::class))
            ->toThrow(InvalidArgumentException::class, 'exceeds the maximum nesting depth of 64');
    }
});

test('bounds a serializer that returns a fresh object on every call', function () {
    $provider = new ConfigurableModelMetadataProvider(new FreshObjectJsonSerializableMetadataValue);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    // No object identity ever repeats and no array level is crossed, so only the depth guard can stop this.
    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(InvalidArgumentException::class, 'exceeds the maximum nesting depth of 64');
});

test('rejects an int-backed enum case outside the JavaScript safe range', function () {
    $provider = new ConfigurableModelMetadataProvider(['snowflake' => UnsafeIntegerBackedStatus::Huge]);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    expect(fn () => new ModelMetadataTransformer(User::class))
        ->toThrow(
            InvalidArgumentException::class,
            'property [value.snowflake] exceeds JavaScript\'s safe integer range (±9007199254740991); return it as a string.',
        );
});

test('spells a list member by the object literal key its type declares', function () {
    config()->set('ts-publish.model_metadata.provider_class', TupleShapeMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->propertyTypes['tuple'])->toBe('{ 0: Record<string, number>; 1: number[] }')
        ->and($data->properties['tuple'][0])->toBeInstanceOf(stdClass::class)
        ->and($data->properties['tuple'][1])->toBe([]);
});

test('keeps an explicit empty stdClass under a type that is not object-like', function () {
    $provider = new ConfigurableModelMetadataProvider(['a' => (object) [], 'b' => (object) []]);
    app()->instance(ConfigurableModelMetadataProvider::class, $provider);
    config()->set('ts-publish.model_metadata.provider_class', ConfigurableModelMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    // `unknown` is not object-like, so coercion cannot re-manufacture these: only the provider's (object) [] can.
    expect($data->propertyTypes['value'])->toBe('unknown')
        ->and($data->properties['value']['a'])->toBeInstanceOf(stdClass::class)
        ->and($data->properties['value']['b'])->toBeInstanceOf(stdClass::class);
});
