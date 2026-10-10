<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DispatchesFqcnResults;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Holds the result of AST analysis of a class method returning an array (e.g. a resource's toArray()).
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type ClassMapType = array<string, class-string>
 * @phpstan-type ImportMapType = TypesImportMap
 * @phpstan-type InlineEnumFqcnsMap = array<string, list<class-string>>
 * @phpstan-type InlineModelFqcnsMap = array<string, list<class-string>>
 * @phpstan-type InlineResourceFqcnsMap = array<string, list<class-string>>
 * @phpstan-type MultiEnumFqcnsMap = array<string, list<class-string>>
 * @phpstan-type EnumResourceArmShape = array{wrapIsCollection: bool, directIsArray: bool}
 * @phpstan-type EnumResourceArmShapeMap = array<string, EnumResourceArmShape>
 * @phpstan-type CastInForce = array{type: string, import: string|null, optional?: bool}
 * @phpstan-type CastMap = array<string, CastInForce>
 * @phpstan-type CarriedMap = array{
 *     enums?: list<class-string>,
 *     models?: list<class-string>,
 *     resources?: list<class-string>,
 * }
 * @phpstan-type AnalyzedProperty = array{
 *     name: string,
 *     type: string,
 *     optional: bool,
 *     description: string,
 *     bodyType?: string,
 *     fillType?: string,
 * }
 * @phpstan-type AnalyzedPropertyList = list<AnalyzedProperty>
 *
 * @internal
 */
class MethodAnalysis
{
    use DispatchesFqcnResults;

    /**
     * @param  AnalyzedPropertyList  $properties  `bodyType` is set only on an index signature whose value a docblock
     *                                            fill or a same-pattern union changed: the value its body gives it;
     *                                            `fillType` is that signature's docblock fill, kept through a put-back
     * @param  ClassMapType  $enumResources  property name => enum FQCN (via EnumResource::make)
     * @param  ClassMapType  $nestedResources  property name => resource FQCN
     * @param  ImportMapType  $customImports  import path => list of type names
     * @param  ClassMapType  $directEnumFqcns  property name => FQCN for direct access; FQCN => FQCN for embedded enums
     * @param  ClassMapType  $modelFqcns  property name => model FQCN (from bare whenLoaded)
     * @param  InlineEnumFqcnsMap  $inlineEnumFqcns  property name => list of enum FQCNs embedded in inline object type strings
     * @param  InlineModelFqcnsMap  $inlineModelFqcns  property name => list of model FQCNs embedded in inline object type strings
     * @param  InlineResourceFqcnsMap  $inlineResourceFqcns  property name => the resource FQCN behind each resource
     *                                                       token, in type order
     * @param  MultiEnumFqcnsMap  $multiEnumResourceFqcns  property name => ordered list of enum FQCNs (for multi-EnumResource ternary/union branches, used for AsEnum rewrite)
     * @param  InlineEnumFqcnsMap  $inlineEnumResourceFqcns  property name => list of enum FQCNs embedded via EnumResource in inline object type strings (used for value imports)
     * @param  EnumResourceArmShapeMap  $enumResourceArmShapes  property name => each arm's own array shape,
     *                                                          for a mixed EnumResource/direct-access ternary or match
     * @param  CastMap  $casts  property name => the text a method-level #[TsCasts] entry wrote for it, the path of its
     *                          own import and its `optional` flag, if any; a publisher fits the key's import channels
     *                          to that text
     * @param  CarriedMap  $carried  per kind, each class a cast displaced and its text does not spell: in no import
     *                               channel, and imported only for a text with no class of its name behind it
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
        public array $inlineResourceFqcns = [],
        public array $multiEnumResourceFqcns = [],
        public array $inlineEnumResourceFqcns = [],
        public array $enumResourceArmShapes = [],
        public array $casts = [],
        public array $carried = [],
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
        // A class no generated file exports would be a token with no import behind it.
        if (! ValueResult::namesOnlyExportedClasses($result)) {
            $result = [...ValueResult::unknown(), 'optional' => $result['optional']];
        }

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

        foreach ($result['embeddedResourceFqcns'] ?? [] as $fqcn) {
            $this->inlineResourceFqcns[$name][] = $fqcn;
        }

        foreach ($result['embeddedEnumResourceFqcns'] ?? [] as $fqcn) {
            $this->inlineEnumResourceFqcns[$name][] = $fqcn;
        }

        foreach ($result['customImports'] ?? [] as $path => $types) {
            $this->customImports[$path] = [...($this->customImports[$path] ?? []), ...$types];
        }

        $this->carry($result['carriedFqcns'] ?? []);
    }

    /**
     * Merge another analysis's maps into this one.
     *
     * `properties` appends; the single-value class maps spread-merge and `casts` replace-merges, the source winning,
     * and a key the source sets without a cast loses its entry. The four inline maps append WITHOUT deduping:
     * aliasPropertyType() consumes each as a positional queue against the rendered type. `carried` unions per kind.
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

        foreach ($source->properties as $property) {
            if (! isset($source->casts[$property['name']])) {
                unset($this->casts[$property['name']]);
            }
        }

        // A spread would renumber a numeric cast key.
        $this->casts = array_replace($this->casts, $source->casts);
        $this->carry($source->carried);

        foreach ($source->customImports as $path => $types) {
            $this->customImports[$path] = [...($this->customImports[$path] ?? []), ...$types];
        }

        foreach ($source->inlineEnumFqcns as $propName => $fqcns) {
            $this->inlineEnumFqcns[$propName] = [...($this->inlineEnumFqcns[$propName] ?? []), ...$fqcns];
        }

        foreach ($source->inlineModelFqcns as $propName => $fqcns) {
            $this->inlineModelFqcns[$propName] = [...($this->inlineModelFqcns[$propName] ?? []), ...$fqcns];
        }

        foreach ($source->inlineResourceFqcns as $propName => $fqcns) {
            $this->inlineResourceFqcns[$propName] = [...($this->inlineResourceFqcns[$propName] ?? []), ...$fqcns];
        }

        foreach ($source->inlineEnumResourceFqcns as $propName => $fqcns) {
            $this->inlineEnumResourceFqcns[$propName] = [
                ...($this->inlineEnumResourceFqcns[$propName] ?? []), ...$fqcns,
            ];
        }
    }

    /**
     * Add classes a cast carried, once each per kind.
     *
     * @param  CarriedMap  $carried
     */
    public function carry(array $carried): void
    {
        foreach ($carried as $kind => $fqcns) {
            if ($fqcns !== []) {
                $this->carried[$kind] = array_values(array_unique([...($this->carried[$kind] ?? []), ...$fqcns]));
            }
        }
    }

    /** Whether any FQCN channel carries an entry for this property name, whose tokens are rewritten under it. */
    public function hasFqcnChannel(string $name): bool
    {
        return isset($this->enumResources[$name]) || isset($this->nestedResources[$name])
            || isset($this->directEnumFqcns[$name]) || isset($this->modelFqcns[$name])
            || isset($this->inlineEnumFqcns[$name]) || isset($this->inlineModelFqcns[$name])
            || isset($this->inlineResourceFqcns[$name])
            || isset($this->multiEnumResourceFqcns[$name]) || isset($this->inlineEnumResourceFqcns[$name])
            || isset($this->enumResourceArmShapes[$name]);
    }

    /**
     * Forget every import channel entry and cast entry keyed by this property name, when another value takes the key
     * over: an import channel entry left behind would alias the new type by the old one's classes or keep an import
     * nothing spells, and a cast entry would fit the new value to a text the key no longer publishes.
     */
    public function forgetChannels(string $name): void
    {
        unset(
            $this->enumResources[$name], $this->nestedResources[$name], $this->directEnumFqcns[$name],
            $this->modelFqcns[$name], $this->multiEnumResourceFqcns[$name], $this->inlineEnumFqcns[$name],
            $this->inlineModelFqcns[$name], $this->inlineResourceFqcns[$name], $this->inlineEnumResourceFqcns[$name],
            $this->enumResourceArmShapes[$name], $this->casts[$name],
        );
    }

    /**
     * Drop each named key another analysis already holds, with its import channels and cast; signature entries stay.
     *
     * PHP's `+` and Laravel's mergeData() keep a key already set, while a signature entry is one more runtime key.
     */
    public function dropKeysHeldBy(self $held): void
    {
        $names = array_flip(array_column($held->properties, 'name'));
        $kept = [];

        foreach ($this->properties as $property) {
            if (isset($names[$property['name']]) && ! JsEmitter::isIndexSignatureKey($property['name'])) {
                $this->forgetChannels($property['name']);

                continue;
            }

            $kept[] = $property;
        }

        $this->properties = $kept;
    }
}
