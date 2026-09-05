<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\Metadata\ModelMetadataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Dtos\TsModelMetadataDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Metadata\ModelMetadataProviderResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsCastsImportResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use ReflectionClass;
use stdClass;
use UnitEnum;

/**
 * @extends CoreTransformer<Model>
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type NormalizedModelMetadataValue null|bool|int|float|string|array<array-key, mixed>|stdClass
 */
class ModelMetadataTransformer extends CoreTransformer
{
    use SnapshotsTransformerState;

    /** Kebab-cased model names carry no underscore, so only a companion filename ends in this suffix. */
    public const string FILENAME_SUFFIX = '_meta';

    private const int MAX_METADATA_VALUE_DEPTH = 64;

    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

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

    protected ModelMetadataAnalysis $analysis;

    /**
     * Companion filename for a model class, without its TypeScript extension.
     */
    public static function filenameFor(string $modelClass): string
    {
        return Str::kebab(class_basename($modelClass)).static::FILENAME_SUFFIX;
    }

    /**
     * Whether a barrel export names a metadata companion rather than a model interface.
     */
    public static function isMetadataFilename(string $filename): bool
    {
        return str_ends_with($filename, static::FILENAME_SUFFIX);
    }

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
            ->transformProperties()
            ->coerceEmptyArrays();

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
        return self::filenameFor($this->findable);
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
     * Resolve declared, overridden, and body-inferred property types.
     */
    protected function transformPropertyTypes(): static
    {
        $this->analysis = resolve(ModelMetadataAnalyzer::class)
            ->analyze($this->provider::class, array_keys($this->metadata), $this->namespacePath);

        return $this;
    }

    /**
     * Validate the payload keys against the analysis.
     */
    protected function validateProperties(): static
    {
        $payloadKeys = array_keys($this->metadata);
        $undeclaredKeys = $this->analysis->undeclaredKeys($payloadKeys);

        if ($undeclaredKeys !== []) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] returned keys without inferred or declared types: ["
                .implode(', ', $undeclaredKeys).']',
            );
        }

        $missingKeys = $this->analysis->missingKeys($payloadKeys);

        if ($missingKeys !== []) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] is missing required keys: [".implode(', ', $missingKeys).']',
            );
        }

        foreach ($this->analysis->importFreeKeys($payloadKeys) as $property) {
            $type = $this->analysis->types[$property];

            if (LaravelTsPublish::shapeValueHasUnimportableToken($type, $this->analysis->importedNames())) {
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
            array_intersect_key($this->analysis->types, $this->metadata),
            array_intersect_key($this->analysis->importPaths, $this->metadata),
        );

        foreach (array_keys($this->metadata) as $property) {
            $this->propertyTypes[$property] = $resolved['overrides'][$property];
        }

        $typeImports = $resolved['typeImports'];

        foreach ($this->analysis->typeImports as $path => $names) {
            $typeImports[$path] = array_values(array_unique([...($typeImports[$path] ?? []), ...$names]));
            sort($typeImports[$path]);
        }

        ksort($typeImports);

        $seen = [];

        foreach ($typeImports as $path => $names) {
            foreach ($names as $name) {
                $local = str_contains($name, ' as ') ? trim(substr($name, strrpos($name, ' as ') + 4)) : $name;

                if (isset($seen[$local]) && $seen[$local] !== $path) {
                    throw new InvalidArgumentException(
                        "Model metadata for model [{$this->findable}] imports [{$local}] from both [{$seen[$local]}] and [{$path}]; "
                        .'declare one of them with an import-aware #[TsCasts] alias.',
                    );
                }

                $seen[$local] = $path;
            }
        }

        $this->typeImports = $typeImports;

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

    /**
     * Turn an empty PHP array into an empty object wherever its resolved type is object-like.
     */
    protected function coerceEmptyArrays(): static
    {
        foreach ($this->properties as $property => $value) {
            $this->properties[$property] = $this->coerceEmptyArray($value, $this->propertyTypes[$property]);
        }

        return $this;
    }

    /** @return list<string> */
    protected function transientProperties(): array
    {
        return ['modelInstance', 'provider', 'metadata', 'analysis'];
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
     * Walk one value alongside its declared type, spelling `[]` as `{}` where the type says object.
     *
     * PHP cannot tell `[]` from `{}`, and the engine's DTOs export only the type string, so an opaque type keeps `[]`.
     *
     * @param  NormalizedModelMetadataValue  $value
     * @return NormalizedModelMetadataValue
     */
    private function coerceEmptyArray(
        null|bool|int|float|string|array|stdClass $value,
        ?string $type,
    ): null|bool|int|float|string|array|stdClass {
        if ($value === []) {
            return $type !== null && TsTypeShape::isObjectLike($type) ? new stdClass : [];
        }

        if (! is_array($value) || $type === null) {
            return $value;
        }

        // A PHP list can carry an object literal's numeric keys (`array{0: ..., 1: ...}`), so a list falls back
        // to the member accessor. The reverse has no payload that it fixes: an object literal typed as an array
        // fails tsc however its members are spelled, so an assoc array consults the member accessor only.
        $isList = array_is_list($value);
        $elementType = $isList ? TsTypeShape::elementType($type) : null;

        foreach ($value as $key => $nested) {
            /** @var NormalizedModelMetadataValue $nested */
            $value[$key] = $this->coerceEmptyArray(
                $nested,
                $elementType ?? TsTypeShape::memberType($type, (string) $key),
            );
        }

        return $value;
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
    ): null|bool|int|float|string|array|stdClass {
        if ($depth > self::MAX_METADATA_VALUE_DEPTH) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] property [{$path}] exceeds the maximum nesting depth of "
                .self::MAX_METADATA_VALUE_DEPTH.'.',
            );
        }

        if ($value === null || is_bool($value) || is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            if (abs($value) > self::MAX_SAFE_INTEGER) {
                throw new InvalidArgumentException(
                    "Model metadata for model [{$this->findable}] property [{$path}] exceeds JavaScript's safe integer "
                    .'range (±'.self::MAX_SAFE_INTEGER.'); return it as a string.',
                );
            }

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

        if ($value instanceof UnitEnum) {
            return $this->normalizeMetadataValue(LaravelTsPublish::enumScalar($value), $path, $depth, $objectStack);
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

        if (! $value instanceof stdClass && ! $value instanceof Arrayable && ! $value instanceof JsonSerializable) {
            throw new InvalidArgumentException(
                "Model metadata for model [{$this->findable}] property [{$path}] returned unsupported value "
                .'['.get_debug_type($value).']. Expected a scalar, array, enum, stdClass, Arrayable, or JsonSerializable value.',
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
            if ($value instanceof stdClass) {
                $properties = get_object_vars($value);

                // The one value PHP can spell as an empty object; a bare [] is disambiguated later by its type.
                return $properties === []
                    ? new stdClass
                    : $this->normalizeMetadataValue($properties, $path, $depth, $objectStack);
            }

            $serialized = $value instanceof Arrayable ? $value->toArray() : $value->jsonSerialize();

            // An object wrapping an array is not a level of its own; one wrapping another object must cost
            // one, or a serializer returning a fresh object each call would recurse until memory ran out.
            return $this->normalizeMetadataValue(
                $serialized,
                $path,
                $depth + (is_array($serialized) ? 0 : 1),
                $objectStack,
            );
        } finally {
            unset($objectStack[$objectId]);
        }
    }
}
