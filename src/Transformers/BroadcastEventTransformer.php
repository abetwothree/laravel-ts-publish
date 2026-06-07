<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\SurveyorTypeMapper;
use AbeTwoThree\LaravelTsPublish\Dtos\TsBroadcastEventDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Contracts\Type;
use Laravel\Surveyor\Types\StringType;
use Override;
use ReflectionClass;

/**
 * Transforms a broadcast event class into a TsBroadcastEventDto ready for
 * TypeScript type generation.
 *
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
                'type'     => SurveyorTypeMapper::convert($type),
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
}
