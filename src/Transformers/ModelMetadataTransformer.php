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
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use ReflectionClass;
use UnitEnum;

/**
 * @extends CoreTransformer<Model>
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type NormalizedModelMetadataValue null|bool|int|float|string|array<array-key, mixed>
 */
class ModelMetadataTransformer extends CoreTransformer
{
    use SnapshotsTransformerState;

    /** Kebab-cased model names carry no underscore, so only a companion filename ends in this suffix. */
    public const string FILENAME_SUFFIX = '_meta';

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
        $this->analysis = resolve(ModelMetadataAnalyzer::class)->analyze($this->provider::class, array_keys($this->metadata));

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
            array_intersect_key($this->analysis->types, $this->metadata),
            array_intersect_key($this->analysis->importPaths, $this->metadata),
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
