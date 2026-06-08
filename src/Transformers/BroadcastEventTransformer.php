<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\SurveyorTypeMapper;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Dtos\TsBroadcastEventDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type;
use Laravel\Surveyor\Types\IntersectionType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\UnionType;
use Override;
use ReflectionClass;
use UnitEnum;
/**
 * Transforms a broadcast event class into a TsBroadcastEventDto ready for
 * TypeScript type generation.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type PropertyInfo from TsBroadcastEventDto
 * @phpstan-import-type PropertiesList from TsBroadcastEventDto
 *
 * @extends CoreTransformer<ShouldBroadcast>
 */
class BroadcastEventTransformer extends CoreTransformer
{
    /** Short PHP class name, e.g. 'OrderShipped'. */
    public protected(set) string $eventName;

    /**
     * The Echo event string: '.Namespace.ClassName' for default events,
     * or the literal broadcastAs() return value for custom names.
     */
    public protected(set) string $broadcastName;

    /** Absolute path to the PHP source file. */
    public protected(set) string $filePath;

    /** Namespace-based directory path, e.g. 'workbench/app/events'. */
    public protected(set) string $namespacePath;

    /**
     * Payload property map: name → ['type' => 'number', 'optional' => false].
     *
     * @var PropertiesList
     */
    public protected(set) array $properties = [];

    /**
     * Model FQCNs found in properties (FQCN => short TS type name).
     *
     * @var array<class-string, string>
     */
    protected array $modelFqcnMap = [];

    /**
     * Enum FQCNs found in properties (FQCN => 'NameType').
     *
     * @var array<class-string, string>
     */
    protected array $enumFqcnMap = [];

    /**
     * Resolved type imports: import path => list of type names.
     *
     * @var TypesImportMap
     */
    public protected(set) array $typeImports = [];

    /**
     * @param  class-string<ShouldBroadcast>  $findable
     */
    public function __construct(
        string $findable,
        protected Analyzer $analyzer,
    ) {
        parent::__construct($findable);
    }

    #[Override]
    public function transform(): self
    {
        $this->analyzer->analyzeClass($this->findable);

        /** @var ClassResult $analyzed */
        $analyzed = $this->analyzer->result();

        $this->eventName = (new ReflectionClass($this->findable))->getShortName();
        $this->filePath = $analyzed->filePath();
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);
        $this->broadcastName = $this->resolveBroadcastName($analyzed);
        $this->properties = $this->resolveProperties($analyzed);
        $this->typeImports = $this->buildTypeImports();

        return $this;
    }

    #[Override]
    public function filename(): string
    {
        return $this->eventName;
    }

    #[Override]
    public function data(): TsBroadcastEventDto
    {
        return new TsBroadcastEventDto(
            eventName: $this->eventName,
            broadcastName: $this->broadcastName,
            fqcn: $this->fqcn(),
            description: '@see '.$this->fqcn(),
            filename: $this->filename(),
            namespacePath: $this->namespacePath,
            properties: $this->properties,
            typeImports: $this->typeImports,
        );
    }

    /**
     * Resolve the Echo broadcast event string.
     *
     * Uses the literal return value of broadcastAs() when the method is present
     * and Surveyor can statically infer a string literal, otherwise falls back
     * to '.FQCN.With.Dots' (leading dot, backslashes → dots).
     */
    protected function resolveBroadcastName(ClassResult $analyzed): string
    {
        if ($analyzed->hasMethod('broadcastAs')) {
            $returnType = $analyzed->getMethod('broadcastAs')->returnType();

            if ($returnType instanceof StringType && $returnType->value !== null && $returnType->value !== '') {
                return $returnType->value;
            }
        }

        return '.'.str_replace('\\', '.', $this->findable);
    }

    /**
     * Resolve the payload properties from broadcastWith() or public constructor props.
     *
     * @return PropertiesList
     */
    protected function resolveProperties(ClassResult $analyzed): array
    {
        $arrayType = $this->resolveArrayType($analyzed);

        /** @var PropertiesList $result */
        $result = [];

        foreach ($arrayType->value as $name => $type) {
            if (! $type instanceof Type) {
                continue;
            }

            $result[(string) $name] = [
                'type' => $this->convertType($type),
                'optional' => $type->isOptional(),
            ];
        }

        return $result;
    }

    /**
     * Get an ArrayType representing the event payload.
     *
     * Uses broadcastWith() return type when the method exists and returns an
     * ArrayType, otherwise collects all public properties from the class.
     */
    protected function resolveArrayType(ClassResult $analyzed): ArrayType
    {
        if ($analyzed->hasMethod('broadcastWith')) {
            $returnType = $analyzed->getMethod('broadcastWith')->returnType();

            if ($returnType instanceof ArrayType) {
                return $returnType;
            }
        }

        return new ArrayType(
            collect($analyzed->publicProperties())
                ->mapWithKeys(fn ($prop) => [$prop->name => $prop->type])
                ->all(),
        );
    }

    /**
     * Convert a Surveyor type to a TypeScript string, detecting PHP enums and
     * Eloquent models for tracking and proper TS type generation.
     *
     * - Backed PHP enums (int/string) → '{Name}Type', tracked in $enumFqcnMap
     * - Pure PHP enums → '{Name}Type', tracked in $enumFqcnMap
     * - Eloquent models → 'Partial<{Name}>', tracked in $modelFqcnMap
     * - Union/Intersection types → recurse into members
     * - All other types → delegate to SurveyorTypeMapper
     */
    protected function convertType(Type $type): string
    {
        if ($type instanceof ClassType) {
            return $this->convertClassType($type);
        }

        if ($type instanceof UnionType) {
            /** @var array<array-key, mixed> $types */
            $types = $type->types;
            $parts = array_map(
                fn (mixed $t): string => $t instanceof Type ? $this->convertType($t) : 'unknown',
                $types,
            );

            return implode(' | ', $parts);
        }

        if ($type instanceof IntersectionType) {
            /** @var array<array-key, mixed> $types */
            $types = $type->types;
            $parts = array_map(
                fn (mixed $t): string => $t instanceof Type ? $this->convertType($t) : 'unknown',
                $types,
            );

            return implode(' & ', $parts);
        }

        return SurveyorTypeMapper::convert($type);
    }

    /**
     * Convert a ClassType, intercepting PHP enums and Eloquent models.
     *
     * PHP enums become '{NameType}' (importing from the enum module).
     * Eloquent models become 'Partial<Name>' (importing the model interface).
     * Other known classes fall through to SurveyorTypeMapper.
     */
    protected function convertClassType(ClassType $type): string
    {
        $fqcn = ltrim($type->value, '\\');
        $nullSuffix = $type->isNullable() ? ' | null' : '';

        if (enum_exists($fqcn)) {
            $typeName = class_basename($fqcn).'Type';
            /** @var class-string<UnitEnum> $fqcn */
            $this->enumFqcnMap[$fqcn] = $typeName;

            return $typeName.$nullSuffix;
        }

        if (class_exists($fqcn) && is_subclass_of($fqcn, Model::class)) {
            $typeName = class_basename($fqcn);
            /** @var class-string<Model> $fqcn */
            $this->modelFqcnMap[$fqcn] = $typeName;

            return 'Partial<'.$typeName.'>'.$nullSuffix;
        }

        return SurveyorTypeMapper::convert($type);
    }

    /**
     * Build the TypeScript type import map from tracked model and enum FQCNs.
     *
     * Uses LaravelTsPublish::namespaceToPath() and relativeImportPath() to compute
     * the correct relative path from this event's namespace to each dependency.
     *
     * @return TypesImportMap
     */
    protected function buildTypeImports(): array
    {
        /** @var TypesImportMap $imports */
        $imports = [];

        foreach ($this->modelFqcnMap as $fqcn => $typeName) {
            $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
            $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
            $imports[$importPath][] = $typeName;
        }

        foreach ($this->enumFqcnMap as $fqcn => $typeName) {
            $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
            $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
            $imports[$importPath][] = $typeName;
        }

        foreach ($imports as $path => $types) {
            $unique = array_values(array_unique($types));
            sort($unique);
            $imports[$path] = $unique;
        }

        return LaravelTsPublish::sortImportPaths($imports);
    }
}
