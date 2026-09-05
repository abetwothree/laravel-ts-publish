<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Dtos\TsModelMetadataDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Metadata\ModelMetadataProviderResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsCastsImportResolver;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use ReflectionClass;
use ReflectionMethod;
use Throwable;
use UnitEnum;

/**
 * @extends CoreTransformer<Model>
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type TsCastsResult from ParsesTsCasts
 *
 * @phpstan-type NormalizedModelMetadataValue null|bool|int|float|string|array<array-key, mixed>
 * @phpstan-type ProviderDeclaredTypes array{
 *     overrides: array<string, string>,
 *     requiredKeys: array<string, true>,
 *     optionalKeys: array<string, true>,
 * }
 * @phpstan-type ModelMetadataTypes array{
 *     overrides: array<string, string>,
 *     importPaths: array<string, string>,
 *     requiredKeys: array<string, true>,
 *     inferredKeys: array<string, true>,
 * }
 */
class ModelMetadataTransformer extends CoreTransformer
{
    use ParsesTsCasts;
    use SnapshotsTransformerState;

    private const int MAX_METADATA_VALUE_DEPTH = 64;

    public protected(set) string $modelName;

    /** @var array<string, NormalizedModelMetadataValue> */
    public protected(set) array $properties = [];

    /** @var array<string, string> */
    public protected(set) array $propertyTypes = [];

    /** @var TypesImportMap */
    public protected(set) array $typeImports = [];

    protected Model $modelInstance;

    protected ModelMetadataProvider $provider;

    /** @var array<string, mixed> */
    protected array $metadata;

    /** @var ModelMetadataTypes */
    protected array $metadataTypes;

    /**
     * Transform a model into runtime metadata.
     *
     * @return static
     */
    #[Override]
    public function transform(): self
    {
        $this->initInstance()
            ->resolveProvider()
            ->collectMetadata()
            ->transformPropertyTypes()
            ->validateProperties()
            ->resolveImports()
            ->transformProperties();

        return $this;
    }

    /**
     * Get the transformed model metadata.
     */
    #[Override]
    public function data(): TsModelMetadataDto
    {
        return new TsModelMetadataDto(
            modelName: $this->modelName,
            filename: $this->filename(),
            properties: $this->properties,
            propertyTypes: $this->propertyTypes,
            typeImports: $this->typeImports,
        );
    }

    /**
     * Get the metadata companion filename without its TypeScript extension.
     */
    #[Override]
    public function filename(): string
    {
        return Str::kebab($this->modelName).'_meta';
    }

    /**
     * Initialize the model metadata transformation state.
     */
    protected function initInstance(): static
    {
        $reflection = new ReflectionClass($this->findable);

        /** @var Model $modelInstance */
        $modelInstance = resolve($this->findable);
        $this->modelInstance = $modelInstance;
        $this->modelName = $reflection->getShortName();
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);

        return $this;
    }

    /**
     * Resolve the configured metadata provider.
     */
    protected function resolveProvider(): static
    {
        $this->provider = resolve(ModelMetadataProviderResolver::class)->resolve();
        DependencyRecorder::recordClass($this->provider::class);

        return $this;
    }

    /**
     * Collect and validate the provider's runtime payload.
     */
    protected function collectMetadata(): static
    {
        $this->metadata = $this->validateMetadata($this->provider->provide($this->modelInstance));

        return $this;
    }

    /**
     * Parse declared and overridden property types.
     */
    protected function transformPropertyTypes(): static
    {
        $this->metadataTypes = $this->parseProviderTypes($this->provider);

        return $this;
    }

    /**
     * Validate the payload keys against its declared property types.
     */
    protected function validateProperties(): static
    {
        $undeclaredKeys = array_keys(array_diff_key($this->metadata, $this->metadataTypes['overrides']));

        if ($undeclaredKeys !== []) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] returned keys without inferred or declared types: ["
                .implode(', ', $undeclaredKeys).']',
            );
        }

        $missingKeys = array_keys(array_diff_key($this->metadataTypes['requiredKeys'], $this->metadata));

        if ($missingKeys !== []) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] is missing required keys: ["
                .implode(', ', $missingKeys).']',
            );
        }

        foreach (array_intersect_key($this->metadataTypes['inferredKeys'], $this->metadata) as $property => $_) {
            $type = $this->metadataTypes['overrides'][$property];

            if (LaravelTsPublish::shapeValueHasUnimportableToken($type)) {
                throw new InvalidArgumentException(
                    "Model metadata type [{$type}] for property [{$property}] cannot infer an import; declare it with #[TsCasts].",
                );
            }
        }

        return $this;
    }

    /**
     * Resolve imported type aliases and property types.
     */
    protected function resolveImports(): static
    {
        $resolved = resolve(TsCastsImportResolver::class)->resolve(
            array_intersect_key($this->metadataTypes['overrides'], $this->metadata),
            array_intersect_key($this->metadataTypes['importPaths'], $this->metadata),
        );

        foreach (array_keys($this->metadata) as $property) {
            $this->propertyTypes[$property] = $resolved['overrides'][$property];
        }

        $this->typeImports = $resolved['typeImports'];

        return $this;
    }

    /**
     * Transform each provider-supplied metadata property.
     */
    protected function transformProperties(): static
    {
        foreach ($this->metadata as $property => $value) {
            /** @var array<int, true> $objectStack */
            $objectStack = [];
            $this->properties[$property] = $this->normalizeMetadataValue(
                $value,
                $property,
                0,
                $objectStack,
            );
        }

        return $this;
    }

    /** @return list<string> */
    protected function transientProperties(): array
    {
        return ['modelInstance', 'provider', 'metadata', 'metadataTypes'];
    }

    /**
     * Combine body inference, return-shape declarations, and TsCasts overrides.
     *
     * @return ModelMetadataTypes
     */
    protected function parseProviderTypes(ModelMetadataProvider $provider): array
    {
        $method = new ReflectionMethod($provider, 'provide');

        return $this->normalizeProviderTypes(
            $this->inferProviderTypes($provider),
            $this->parseProviderDeclaredTypes($method),
            $this->parseProviderTsCasts($method),
        );
    }

    /**
     * Parse the provider's optional return-shape declarations.
     *
     * @return ProviderDeclaredTypes
     */
    protected function parseProviderDeclaredTypes(ReflectionMethod $method): array
    {
        $types = [];
        $requiredKeys = [];
        $optionalKeys = [];

        foreach (LaravelTsPublish::parseDocblockReturnArrayShape($method) as $property => $type) {
            $optional = str_ends_with($property, '?');
            $property = $optional ? substr($property, 0, -1) : $property;
            $types[$property] = $type;

            if ($optional) {
                $optionalKeys[$property] = true;
            } else {
                $requiredKeys[$property] = true;
            }
        }

        return [
            'overrides' => $types,
            'requiredKeys' => $requiredKeys,
            'optionalKeys' => $optionalKeys,
        ];
    }

    /**
     * Parse TsCasts declared on the provider method.
     *
     * @return TsCastsResult
     */
    protected function parseProviderTsCasts(ReflectionMethod $method): array
    {
        $castTypes = [];

        foreach ($method->getAttributes(TsCasts::class) as $attribute) {
            $castTypes = array_merge($castTypes, $attribute->newInstance()->types);
        }

        return $this->normalizeTsCasts($castTypes);
    }

    /**
     * Normalize provider types with AST, PHPDoc, then TsCasts precedence.
     *
     * @param  array<string, string>  $inferredTypes
     * @param  ProviderDeclaredTypes  $declaredTypes
     * @param  TsCastsResult  $casts
     * @return ModelMetadataTypes
     */
    protected function normalizeProviderTypes(array $inferredTypes, array $declaredTypes, array $casts): array
    {
        $types = [
            ...array_diff_key($inferredTypes, $declaredTypes['overrides'], $casts['overrides']),
            ...$declaredTypes['overrides'],
        ];
        $requiredKeys = $declaredTypes['requiredKeys'];

        $inferredKeys = array_fill_keys(array_keys(array_diff_key($types, $casts['overrides'])), true);

        foreach ($casts['overrides'] as $property => $type) {
            $types[$property] = $type;

            if ($casts['optionalOverrides'][$property] ?? isset($declaredTypes['optionalKeys'][$property])) {
                unset($requiredKeys[$property]);
            } else {
                $requiredKeys[$property] = true;
            }
        }

        return [
            'overrides' => $types,
            'importPaths' => $casts['importPaths'],
            'requiredKeys' => $requiredKeys,
            'inferredKeys' => $inferredKeys,
        ];
    }

    /**
     * Infer concrete, non-unknown property types from the provider method body.
     *
     * @return array<string, string>
     */
    protected function inferProviderTypes(ModelMetadataProvider $provider): array
    {
        try {
            $analysis = resolve(AstEngine::class)->analyzeMethod($provider::class, 'provide');
        } catch (Throwable) {
            return [];
        }

        $types = [];

        foreach ($analysis->properties as $property) {
            if (! array_key_exists($property['name'], $this->metadata)
                || preg_match('/\bunknown\b/', $property['type']) === 1) {
                continue;
            }

            $types[$property['name']] = $property['type'];
        }

        return $types;
    }

    /**
     * Validate the provider's runtime metadata payload.
     *
     * @param  array<array-key, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function validateMetadata(array $metadata): array
    {
        if (array_filter(array_keys($metadata), 'is_int') !== []) {
            throw new InvalidArgumentException('Model metadata payload must use string keys.');
        }

        /** @var array<string, mixed> $metadata */
        return $metadata;
    }

    /**
     * Normalize a provider value into a safely renderable TypeScript literal value.
     *
     * @param  array<int, true>  $objectStack
     * @return NormalizedModelMetadataValue
     */
    private function normalizeMetadataValue(
        mixed $value,
        string $path,
        int $depth,
        array &$objectStack,
    ): null|bool|int|float|string|array {
        if ($depth > self::MAX_METADATA_VALUE_DEPTH) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] property [{$path}] exceeds the maximum nesting depth of "
                .self::MAX_METADATA_VALUE_DEPTH.'.',
            );
        }

        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException(
                    "Model metadata for model [{$this->findable}] property [{$path}] returned a non-finite float.",
                );
            }

            return $value;
        }

        if ($value instanceof BackedEnum) {
            return $this->normalizeMetadataValue($value->value, $path, $depth + 1, $objectStack);
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $nestedValue) {
                $normalized[$key] = $this->normalizeMetadataValue(
                    $nestedValue,
                    $path.'.'.$key,
                    $depth + 1,
                    $objectStack,
                );
            }

            return $normalized;
        }

        if (! $value instanceof Arrayable && ! $value instanceof JsonSerializable) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] property [{$path}] returned unsupported value "
                .'['.get_debug_type($value).']. Expected a scalar, array, enum, Arrayable, or JsonSerializable value.',
            );
        }

        $objectId = spl_object_id($value);

        if (isset($objectStack[$objectId])) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] property [{$path}] contains a circular object value.",
            );
        }

        $objectStack[$objectId] = true;

        try {
            $serialized = $value instanceof Arrayable ? $value->toArray() : $value->jsonSerialize();

            return $this->normalizeMetadataValue($serialized, $path, $depth + 1, $objectStack);
        } finally {
            unset($objectStack[$objectId]);
        }
    }
}
