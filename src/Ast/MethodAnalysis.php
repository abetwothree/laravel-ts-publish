<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DispatchesFqcnResults;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Holds the result of AST analysis of a class method returning an array (e.g. a resource's toArray()).
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type ResourcePropertyInfo = array{
 *     name: string,
 *     type: string,
 *     optional: bool,
 *     description: string,
 * }
 * @phpstan-type ResourcePropertyInfoList = list<ResourcePropertyInfo>
 * @phpstan-type ClassMapType = array<string, class-string>
 * @phpstan-type ImportMapType = TypesImportMap
 * @phpstan-type InlineEnumFqcnsMap = array<string, list<class-string>>
 * @phpstan-type InlineModelFqcnsMap = array<string, list<class-string>>
 * @phpstan-type MultiEnumFqcnsMap = array<string, list<class-string>>
 * @phpstan-type EnumResourceArmShape = array{wrapIsCollection: bool, directIsArray: bool}
 * @phpstan-type EnumResourceArmShapeMap = array<string, EnumResourceArmShape>
 *
 * @internal
 */
class MethodAnalysis
{
    use DispatchesFqcnResults;

    /**
     * @param  ResourcePropertyInfoList  $properties
     * @param  ClassMapType  $enumResources  property name => enum FQCN (via EnumResource::make)
     * @param  ClassMapType  $nestedResources  property name => resource FQCN
     * @param  ImportMapType  $customImports  import path => list of type names
     * @param  ClassMapType  $directEnumFqcns  property name => FQCN for direct access; FQCN => FQCN for embedded enums
     * @param  ClassMapType  $modelFqcns  property name => model FQCN (from bare whenLoaded)
     * @param  InlineEnumFqcnsMap  $inlineEnumFqcns  property name => list of enum FQCNs embedded in inline object type strings
     * @param  InlineModelFqcnsMap  $inlineModelFqcns  property name => list of model FQCNs embedded in inline object type strings
     * @param  MultiEnumFqcnsMap  $multiEnumResourceFqcns  property name => ordered list of enum FQCNs (for multi-EnumResource ternary/union branches, used for AsEnum rewrite)
     * @param  InlineEnumFqcnsMap  $inlineEnumResourceFqcns  property name => list of enum FQCNs embedded via EnumResource in inline object type strings (used for value imports)
     * @param  EnumResourceArmShapeMap  $enumResourceArmShapes  property name => each arm's own array shape,
     *                                                          for a mixed EnumResource/direct-access ternary
     * @param  string|null  $flatTypeAlias  when set, the collection emits `export type X = SingularResource[]` instead of an interface
     * @param  class-string<JsonResource>|null  $flatTypeAliasFqcn  FQCN of the singular resource for the flat type alias
     */
    public function __construct(
        public array $properties = [],
        public array $enumResources = [],
        public array $nestedResources = [],
        public array $customImports = [],
        public array $directEnumFqcns = [],
        public array $modelFqcns = [],
        public array $inlineEnumFqcns = [],
        public array $inlineModelFqcns = [],
        public array $multiEnumResourceFqcns = [],
        public array $inlineEnumResourceFqcns = [],
        public array $enumResourceArmShapes = [],
        public ?string $flatTypeAlias = null,
        public ?string $flatTypeAliasFqcn = null,
    ) {}

    /**
     * Record one resolved value as a property and route every FQCN channel it carries.
     *
     * The one place a value becomes a property: a channel added here reaches every collector,
     * where a hand-rolled collector had to be found and updated one at a time.
     *
     * @param  ValueExpressionResult  $result
     */
    public function addProperty(string $name, array $result, bool $optional = false, string $description = ''): void
    {
        $this->properties[] = [
            'name' => $name,
            'type' => $result['type'],
            'optional' => $optional || $result['optional'],
            'description' => $description,
        ];

        $this->dispatchFqcnResults(
            $name, $result, $this->enumResources, $this->directEnumFqcns, $this->nestedResources,
            $this->modelFqcns, $this->multiEnumResourceFqcns, $this->enumResourceArmShapes,
        );

        foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
            $this->inlineEnumFqcns[$name][] = $fqcn;
        }

        foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
            $this->inlineModelFqcns[$name][] = $fqcn;
        }

        foreach ($result['embeddedEnumResourceFqcns'] ?? [] as $fqcn) {
            $this->inlineEnumResourceFqcns[$name][] = $fqcn;
        }

        foreach ($result['customImports'] ?? [] as $path => $types) {
            $this->customImports[$path] = [...($this->customImports[$path] ?? []), ...$types];
        }
    }

    /**
     * Merge another analysis's maps into this one.
     *
     * `properties` appends; the single-value class maps spread-merge with the source winning on
     * collision. `inlineModelFqcns`, `inlineEnumFqcns` and `inlineEnumResourceFqcns` append WITHOUT
     * deduping — aliasPropertyType() consumes each as a positional queue against the rendered type.
     */
    public function merge(self $source): void
    {
        $this->properties = [...$this->properties, ...$source->properties];
        $this->enumResources = [...$this->enumResources, ...$source->enumResources];
        $this->nestedResources = [...$this->nestedResources, ...$source->nestedResources];
        $this->directEnumFqcns = [...$this->directEnumFqcns, ...$source->directEnumFqcns];
        $this->modelFqcns = [...$this->modelFqcns, ...$source->modelFqcns];
        $this->multiEnumResourceFqcns = [...$this->multiEnumResourceFqcns, ...$source->multiEnumResourceFqcns];
        $this->enumResourceArmShapes = [...$this->enumResourceArmShapes, ...$source->enumResourceArmShapes];

        foreach ($source->customImports as $path => $types) {
            $this->customImports[$path] = [...($this->customImports[$path] ?? []), ...$types];
        }

        foreach ($source->inlineEnumFqcns as $propName => $fqcns) {
            $this->inlineEnumFqcns[$propName] = [...($this->inlineEnumFqcns[$propName] ?? []), ...$fqcns];
        }

        foreach ($source->inlineModelFqcns as $propName => $fqcns) {
            $this->inlineModelFqcns[$propName] = [...($this->inlineModelFqcns[$propName] ?? []), ...$fqcns];
        }

        foreach ($source->inlineEnumResourceFqcns as $propName => $fqcns) {
            $this->inlineEnumResourceFqcns[$propName] = [
                ...($this->inlineEnumResourceFqcns[$propName] ?? []), ...$fqcns,
            ];
        }
    }
}
