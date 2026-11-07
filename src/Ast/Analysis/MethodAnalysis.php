<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DispatchesFqcnResults;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TopLevelRenumbering;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Holds the result of AST analysis of a class method returning an array (e.g. a resource's toArray()).
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type HeldNulls from ExpressionHandler
 * @phpstan-import-type Slots from TokenSlots
 *
 * @phpstan-type ClassMapType = array<array-key, class-string>
 * @phpstan-type ImportMapType = TypesImportMap
 * @phpstan-type InlineEnumFqcnsMap = array<array-key, list<class-string>>
 * @phpstan-type InlineModelFqcnsMap = array<array-key, list<class-string>>
 * @phpstan-type InlineResourceFqcnsMap = array<array-key, list<class-string>>
 * @phpstan-type MultiEnumFqcnsMap = array<array-key, list<class-string>>
 * @phpstan-type FqcnQueueMap = array<array-key, list<class-string>>
 * @phpstan-type EnumResourceArmShape = array{wrapIsCollection: bool, directIsArray: bool}
 * @phpstan-type EnumResourceArmShapeMap = array<array-key, EnumResourceArmShape>
 * @phpstan-type CastInForce = array{
 *     type: string,
 *     import: string|null,
 *     optional?: bool,
 *     customImports?: TypesImportMap,
 *     tokenFqcns?: Slots,
 * }
 * @phpstan-type CastMap = array<array-key, CastInForce>
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
 *     keepsKeys?: bool,
 * }
 * @phpstan-type AnalyzedPropertyList = list<AnalyzedProperty>
 *
 * @internal
 */
class MethodAnalysis
{
    use DispatchesFqcnResults;

    /**
     * Whether every read behind these keys was followed. A returned variable built on one that was not is skipped
     * beside a literal return, though the lenient rule can still read it alone.
     */
    public bool $readsCompletely = true;

    /**
     * Whether keys these hold reach the return unread: from a whole write, helper or spread the walk cannot read, a key
     * write it cannot name, an appending by-reference call, or a returned parameter it starts with no seed. Set through
     * holdUnreadKeys(); the body fallback publishes such a shape open.
     */
    public bool $holdsUnreadKeys = false;

    /**
     * Whether a key these hold may be one no name the engine reads names: a key no literal or constant names, or a
     * spread of a value that is no closed shape. An inline object of them holds keys a docblock may name, set or not.
     */
    public bool $holdsUnnamedKeys = false;

    /**
     * Property name => the class behind each token of a class's name in the text a declaration retypes it with, null
     * where an override spells that name, recorded by CastChannels::fit(): a share() docblock entry's at publish time,
     * and a key ReturnShapeRefiner fills from a method's `@return` when the analysis reads it.
     *
     * @var array<array-key, Slots>
     */
    public array $declaredTokenFqcns = [];

    /**
     * Property name => the nulls of a value addProperty() last gave the key that PHP holds as an object on some arm
     * (ValueResult::heldNullsOf()), which a returned variable's walk reads where the write lands. A merge or a join
     * sets none, so its keys read as plain.
     *
     * @var array<array-key, HeldNulls>
     */
    public array $heldObjectKeys = [];

    /**
     * Property name => true where a value addProperty() gave the key may be left out by itself, as an Inertia deferred
     * prop or a `when()` value may, beside a key whose only absence is a write that may not run. Merges and joins keep
     * every branch's.
     *
     * @var array<array-key, true>
     */
    public array $leftOutValues = [];

    /**
     * Property name => true where the value addProperty() last gave the key is an object Inertia replaces before a
     * dotted key writes under it (ValueResult::closedToDottedKeys()). A merge takes the source's; a join keeps a key
     * every branch holding it closes. A fact of the value, so forgetChannels(), which a cast also runs, keeps it.
     *
     * @var array<array-key, true>
     */
    public array $closedToDottedKeys = [];

    /**
     * Property name => each reading its value leaves open (ValueResult `openReadings`): its path in `.key`, `[]` and
     * `{}` steps, a NUL, and its type. A merge takes the source's; a join keeps every branch's.
     *
     * @var array<array-key, list<string>>
     */
    public array $openReadings = [];

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
     * @param  CarriedMap  $carried  per kind, each class a cast displaced and its text does not spell: kept apart from
     *                               every queue, and imported only for a text with no class of its name behind it
     * @param  string|null  $flatTypeAlias  when set, the collection emits `export type X = SingularResource[]` instead of an interface
     * @param  class-string<JsonResource|Model>|null  $flatTypeAliasFqcn  FQCN of the element the flat type alias lists
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
     * Whether a class has an inline channel a key can queue it on: an enum, a model or an API resource.
     */
    public static function queuesEmbedded(string $fqcn): bool
    {
        return enum_exists($fqcn) || is_a($fqcn, Model::class, true) || is_a($fqcn, JsonResource::class, true);
    }

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

            // A signature's untyped value stays `unknown | undefined`, the floor the refiner fills and the reconcile
            // puts back.
            if (JsEmitter::isIndexSignatureKey($name)) {
                $result = ValueResult::asIndexSignatureValue($result);
            }
        }

        $this->properties[] = [
            'name' => $name,
            'type' => $result['type'],
            'optional' => $optional || $result['optional'],
            'description' => $description,
            ...(($result['keepsKeys'] ?? false) ? ['keepsKeys' => true] : []),
        ];

        $held = ValueResult::heldNullsOf($result);

        if ($held === null) {
            unset($this->heldObjectKeys[$name]);
        } else {
            $this->heldObjectKeys[$name] = $held;
        }

        if ($result['optional']) {
            $this->leftOutValues[$name] = true;
        } else {
            unset($this->leftOutValues[$name]);
        }

        if (ValueResult::closedToDottedKeys($result)) {
            $this->closedToDottedKeys[$name] = true;
        } else {
            unset($this->closedToDottedKeys[$name]);
        }

        if (($result['openReadings'] ?? []) === []) {
            unset($this->openReadings[$name]);
        } else {
            $this->openReadings[$name] = $result['openReadings'];
        }

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
     * Mark that keys these hold reach the return unread, so the read behind them is not complete either.
     */
    public function holdUnreadKeys(): void
    {
        $this->readsCompletely = false;
        $this->holdsUnreadKeys = true;
    }

    /**
     * Record one more runtime key a signature covers: it replaces no earlier entry, is never optional, and its value
     * admits `undefined`. It brings no cast, so, as merge() does, the name's earlier cast no longer describes it.
     *
     * @param  ValueExpressionResult  $result
     */
    public function addSignatureEntry(string $name, array $result): void
    {
        unset($this->casts[$name]);
        $this->addProperty($name, ValueResult::asIndexSignatureValue($result));
    }

    /**
     * Merge another analysis's maps into this one.
     *
     * `properties` appends; the class maps, `casts`, `declaredTokenFqcns`, `closedToDottedKeys` and `openReadings`
     * replace-merge, the source winning, a key it sets with none losing its own; the inline maps append undeduped, as
     * read by position. `carried` and `leftOutValues` union; `readsCompletely` ANDs, the `holds…Keys` flags OR.
     */
    public function merge(self $source): void
    {
        $this->properties = [...$this->properties, ...$source->properties];
        $this->readsCompletely = $this->readsCompletely && $source->readsCompletely;
        $this->holdsUnreadKeys = $this->holdsUnreadKeys || $source->holdsUnreadKeys;
        $this->holdsUnnamedKeys = $this->holdsUnnamedKeys || $source->holdsUnnamedKeys;
        // A spread would renumber a numeric property name, which PHP stores as an int.
        $this->enumResources = array_replace($this->enumResources, $source->enumResources);
        $this->nestedResources = array_replace($this->nestedResources, $source->nestedResources);
        $this->directEnumFqcns = array_replace($this->directEnumFqcns, $source->directEnumFqcns);
        $this->modelFqcns = array_replace($this->modelFqcns, $source->modelFqcns);
        $this->multiEnumResourceFqcns = array_replace($this->multiEnumResourceFqcns, $source->multiEnumResourceFqcns);
        $this->enumResourceArmShapes = array_replace($this->enumResourceArmShapes, $source->enumResourceArmShapes);
        $this->leftOutValues = array_replace($this->leftOutValues, $source->leftOutValues);

        foreach ($source->properties as $property) {
            if (! isset($source->casts[$property['name']])) {
                unset($this->casts[$property['name']]);
            }

            if (! isset($source->declaredTokenFqcns[$property['name']])) {
                unset($this->declaredTokenFqcns[$property['name']]);
            }

            unset($this->closedToDottedKeys[$property['name']], $this->openReadings[$property['name']]);
        }

        // A spread would renumber a numeric cast key.
        $this->casts = array_replace($this->casts, $source->casts);
        $this->declaredTokenFqcns = array_replace($this->declaredTokenFqcns, $source->declaredTokenFqcns);
        $this->closedToDottedKeys = array_replace($this->closedToDottedKeys, $source->closedToDottedKeys);
        $this->openReadings = array_replace($this->openReadings, $source->openReadings);
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
     * Take over every key, channel, cast and flag of another analysis, as a returned variable's walk does where
     * exclusive branches join.
     */
    public function replaceWith(self $source): void
    {
        $this->properties = $source->properties;
        $this->readsCompletely = $source->readsCompletely;
        $this->holdsUnreadKeys = $source->holdsUnreadKeys;
        $this->holdsUnnamedKeys = $source->holdsUnnamedKeys;
        $this->enumResources = $source->enumResources;
        $this->nestedResources = $source->nestedResources;
        $this->customImports = $source->customImports;
        $this->directEnumFqcns = $source->directEnumFqcns;
        $this->modelFqcns = $source->modelFqcns;
        $this->inlineEnumFqcns = $source->inlineEnumFqcns;
        $this->inlineModelFqcns = $source->inlineModelFqcns;
        $this->inlineResourceFqcns = $source->inlineResourceFqcns;
        $this->multiEnumResourceFqcns = $source->multiEnumResourceFqcns;
        $this->inlineEnumResourceFqcns = $source->inlineEnumResourceFqcns;
        $this->enumResourceArmShapes = $source->enumResourceArmShapes;
        $this->casts = $source->casts;
        $this->carried = $source->carried;
        $this->declaredTokenFqcns = $source->declaredTokenFqcns;
        $this->heldObjectKeys = $source->heldObjectKeys;
        $this->leftOutValues = $source->leftOutValues;
        $this->closedToDottedKeys = $source->closedToDottedKeys;
        $this->openReadings = $source->openReadings;
        $this->flatTypeAlias = $source->flatTypeAlias;
        $this->flatTypeAliasFqcn = $source->flatTypeAliasFqcn;
    }

    /**
     * Drop what this branch of a join only repeats from another, which would pile up per join: the queue of a key
     * both hold with the same entries and queue, whose type the join spells once, and each custom import both bring.
     */
    public function dropRepeatsOf(self $other): void
    {
        $theirs = [];

        foreach ($other->properties as $property) {
            $theirs[$property['name']][] = $property;
        }

        $mine = [];

        foreach ($this->properties as $property) {
            $mine[$property['name']][] = $property;
        }

        foreach ($mine as $name => $entries) {
            if ($entries !== ($theirs[$name] ?? null)) {
                continue;
            }

            $this->inlineEnumFqcns = self::withoutRepeat($this->inlineEnumFqcns, $other->inlineEnumFqcns, $name);
            $this->inlineModelFqcns = self::withoutRepeat($this->inlineModelFqcns, $other->inlineModelFqcns, $name);
            $this->inlineResourceFqcns = self::withoutRepeat(
                $this->inlineResourceFqcns, $other->inlineResourceFqcns, $name,
            );
            $this->inlineEnumResourceFqcns = self::withoutRepeat(
                $this->inlineEnumResourceFqcns, $other->inlineEnumResourceFqcns, $name,
            );
        }

        foreach ($this->customImports as $path => $types) {
            $this->customImports[$path] = array_values(array_diff($types, $other->customImports[$path] ?? []));
        }
    }

    /**
     * Queue, in branch order, the class each branch of a merge types a key with where two branches type it apart, as a
     * union's arms are: merge() keeps only the last branch's, leaving the others' tokens with no import. Run on the
     * merged analysis, whose types decide whether the enums the branches wrap a key in can be listed per token.
     *
     * @param  list<self>  $branches
     */
    public function queueSplitClasses(array $branches): void
    {
        [$this->nestedResources, $this->inlineResourceFqcns] = self::queuedSplitClasses(
            $this->nestedResources,
            $this->inlineResourceFqcns,
            array_map(fn (self $branch): array => [$branch->nestedResources, $branch->inlineResourceFqcns], $branches),
        );
        [$this->directEnumFqcns, $this->inlineEnumFqcns] = self::queuedSplitClasses(
            $this->directEnumFqcns,
            $this->inlineEnumFqcns,
            array_map(fn (self $branch): array => [$branch->directEnumFqcns, $branch->inlineEnumFqcns], $branches),
        );
        [$this->modelFqcns, $this->inlineModelFqcns] = self::queuedSplitClasses(
            $this->modelFqcns,
            $this->inlineModelFqcns,
            array_map(fn (self $branch): array => [$branch->modelFqcns, $branch->inlineModelFqcns], $branches),
        );
        $this->listSplitEnumResources($branches);
    }

    /**
     * Queue classes a key's text spells inside its value, as addProperty() queues an embedded class: on the key's
     * inline channel of its kind, and keyed by itself. A class of no such kind is left out.
     *
     * @param  list<class-string>  $fqcns
     */
    public function queueEmbeddedClasses(string $name, array $fqcns): void
    {
        foreach ($fqcns as $fqcn) {
            if (enum_exists($fqcn)) {
                $this->inlineEnumFqcns[$name][] = $fqcn;
                $this->directEnumFqcns[$fqcn] = $fqcn;
            } elseif (is_a($fqcn, Model::class, true)) {
                $this->inlineModelFqcns[$name][] = $fqcn;
                $this->modelFqcns[$fqcn] = $fqcn;
            } elseif (is_a($fqcn, JsonResource::class, true)) {
                $this->inlineResourceFqcns[$name][] = $fqcn;
                $this->nestedResources[$fqcn] = $fqcn;
            }
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

    /**
     * Whether any FQCN channel carries an entry for this property name. A direct enum's entry may name an enum whose
     * override the value spells instead of its `…Type`; channelNames() leaves such an enum out.
     */
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
     * The names the classes on a property's import channels publish under: each enum's type, sibling names and const,
     * each model's name and each resource's type. An enum the value holds that LaravelTsPublish::retypedEnumType()
     * answers for brings none, as the value spells that type instead.
     *
     * @return list<string>
     */
    public function channelNames(string $name): array
    {
        $held = array_filter(
            $this->channelClasses($name, $this->directEnumFqcns, $this->inlineEnumFqcns),
            static fn (string $enum): bool => LaravelTsPublish::retypedEnumType($enum) === null,
        );
        $wrapped = $this->wrappedEnumFqcns($name);
        $models = $this->channelClasses($name, $this->modelFqcns, $this->inlineModelFqcns);
        $resources = $this->channelClasses($name, $this->nestedResources, $this->inlineResourceFqcns);

        $names = [
            ...array_map(class_basename(...), $models),
            ...array_map(static fn (string $resource): string => TsNaming::resourceTypeName($resource), $resources),
        ];

        foreach ([...$held, ...$wrapped] as $enum) {
            array_push($names, ValueResult::enumConstName($enum), ...ValueResult::enumNames($enum));
        }

        return array_values(array_unique($names));
    }

    /**
     * The enums, models and resources behind a property's type tokens, each kind in the order its channels queue them.
     *
     * @return list<class-string>
     */
    public function channelFqcns(string $name): array
    {
        return [
            ...(isset($this->enumResources[$name]) ? [$this->enumResources[$name]] : []),
            ...$this->channelClasses($name, $this->directEnumFqcns, $this->multiEnumResourceFqcns),
            ...$this->inlineEnumFqcns[$name] ?? [],
            ...$this->channelClasses($name, $this->modelFqcns, $this->inlineModelFqcns),
            ...$this->channelClasses($name, $this->nestedResources, $this->inlineResourceFqcns),
        ];
    }

    /**
     * Every class on any import channel behind a type's tokens, a self-keyed embedded class's too, once each.
     *
     * @return list<class-string>
     */
    public function allChannelFqcns(): array
    {
        return $this->fqcnsOnChannels(
            [$this->enumResources, $this->directEnumFqcns, $this->modelFqcns, $this->nestedResources],
            [$this->multiEnumResourceFqcns, $this->inlineEnumFqcns, $this->inlineModelFqcns, $this->inlineResourceFqcns],
        );
    }

    /**
     * Every class a cast displaced and its text does not spell, once each.
     *
     * @return list<class-string>
     */
    public function allCarriedFqcns(): array
    {
        return array_values(array_unique(array_merge([], ...array_values($this->carried))));
    }

    /**
     * Every enum any value wraps in an `EnumResource`, once each.
     *
     * @return list<class-string>
     */
    public function allWrappedEnumFqcns(): array
    {
        return $this->fqcnsOnChannels([$this->enumResources], [$this->multiEnumResourceFqcns, $this->inlineEnumResourceFqcns]);
    }

    /**
     * The enums a property's value wraps in an `EnumResource`, whose consts an `AsEnum<typeof …>` spells.
     *
     * @return list<class-string>
     */
    public function wrappedEnumFqcns(string $name): array
    {
        return [
            ...$this->channelClasses($name, $this->enumResources, $this->multiEnumResourceFqcns),
            ...$this->inlineEnumResourceFqcns[$name] ?? [],
        ];
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
            $this->enumResourceArmShapes[$name], $this->casts[$name], $this->declaredTokenFqcns[$name],
            $this->leftOutValues[$name],
        );
    }

    /**
     * Read a key as an untyped value: `unknown`, with no import channel, and without each custom import its text
     * spelled that no other key reads outside a literal. Whether the value may be left out stays, since an untyped
     * value may be too.
     */
    public function untype(string $name): void
    {
        $this->retypeClassLess($name, 'unknown');
    }

    /**
     * Retype a key with a text naming no class, as TokenSlots::classLess() reads one: with no import channel, and
     * without each custom import its old text spelled that neither the new text nor another key reads outside a
     * literal. Whether the value may be left out stays.
     */
    public function retypeClassLess(string $name, string $type): void
    {
        $spelled = [];
        $others = [$type];

        foreach ($this->properties as $index => $property) {
            if ($property['name'] !== $name) {
                $others[] = $property['type'];

                continue;
            }

            $spelled[] = $property['type'];
            $this->properties[$index]['type'] = $type;
            unset($this->properties[$index]['bodyType'], $this->properties[$index]['fillType']);
        }

        $leftOut = isset($this->leftOutValues[$name]);
        $this->forgetChannels($name);

        if ($leftOut) {
            $this->leftOutValues[$name] = true;
        }

        // A literal's spelling binds no import, so only a read token elsewhere keeps one.
        $onlyItsOwn = static fn (string $import): bool => TsTypeString::typeNameOccursIn(ImportMaps::localName($import), ...$spelled)
            && ! TokenSlots::readsName(ImportMaps::localName($import), ...$others);

        foreach ($this->customImports as $path => $names) {
            $kept = array_values(array_filter($names, static fn (string $import): bool => ! $onlyItsOwn($import)));

            if ($kept === []) {
                unset($this->customImports[$path]);
            } else {
                $this->customImports[$path] = $kept;
            }
        }
    }

    /**
     * Publish `unknown` for each member whose own channels name a model with no published file, or a class publishing
     * under a name the custom imports bring, and forget its channels; the other members keep theirs.
     *
     * A reader holding these members in one key declines it for such a model (ValueResult::namesOnlyPublishedModels())
     * and aliases every token of one name alike, so such a class would take the custom import's tokens with it.
     */
    public function forgetUnpublishableMembers(): void
    {
        $imported = ImportMaps::localNames($this->customImports);

        foreach (array_unique(array_column($this->properties, 'name')) as $name) {
            $models = $this->channelClasses($name, $this->modelFqcns, $this->inlineModelFqcns);

            if (ValueResult::everyModelPublished($models)
                && ($imported === [] || array_intersect($this->channelNames($name), $imported) === [])
            ) {
                continue;
            }

            foreach ($this->properties as $index => $property) {
                if ($property['name'] === $name) {
                    $this->properties[$index]['type'] = 'unknown';
                }
            }

            $this->forgetChannels($name);
        }
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

    /**
     * Rename properties, with every import channel, cast and held-object entry kept under their names, at once.
     *
     * @param  array<array-key, string>  $names  each name => its new name; a name it lacks keeps its own
     */
    public function renameKeys(array $names): void
    {
        foreach ($this->properties as &$property) {
            $property['name'] = $names[$property['name']] ?? $property['name'];
        }

        unset($property);

        $this->enumResources = TopLevelRenumbering::rekey($this->enumResources, $names);
        $this->nestedResources = TopLevelRenumbering::rekey($this->nestedResources, $names);
        $this->directEnumFqcns = TopLevelRenumbering::rekey($this->directEnumFqcns, $names);
        $this->modelFqcns = TopLevelRenumbering::rekey($this->modelFqcns, $names);
        $this->inlineEnumFqcns = TopLevelRenumbering::rekey($this->inlineEnumFqcns, $names);
        $this->inlineModelFqcns = TopLevelRenumbering::rekey($this->inlineModelFqcns, $names);
        $this->inlineResourceFqcns = TopLevelRenumbering::rekey($this->inlineResourceFqcns, $names);
        $this->multiEnumResourceFqcns = TopLevelRenumbering::rekey($this->multiEnumResourceFqcns, $names);
        $this->inlineEnumResourceFqcns = TopLevelRenumbering::rekey($this->inlineEnumResourceFqcns, $names);
        $this->enumResourceArmShapes = TopLevelRenumbering::rekey($this->enumResourceArmShapes, $names);
        $this->casts = TopLevelRenumbering::rekey($this->casts, $names);
        $this->declaredTokenFqcns = TopLevelRenumbering::rekey($this->declaredTokenFqcns, $names);
        $this->heldObjectKeys = TopLevelRenumbering::rekey($this->heldObjectKeys, $names);
        $this->leftOutValues = TopLevelRenumbering::rekey($this->leftOutValues, $names);
        $this->closedToDottedKeys = TopLevelRenumbering::rekey($this->closedToDottedKeys, $names);
        $this->openReadings = TopLevelRenumbering::rekey($this->openReadings, $names);
    }

    /**
     * Rewrite every property's type, body type and fill, except a key a cast types and a value that keeps its keys.
     *
     * @param  Closure(string): string  $rewrite
     */
    public function rewritePropertyTypes(Closure $rewrite): void
    {
        foreach ($this->properties as &$property) {
            if (isset($this->casts[$property['name']]) || ($property['keepsKeys'] ?? false)) {
                continue;
            }

            $property['type'] = $rewrite($property['type']);

            foreach (['bodyType', 'fillType'] as $field) {
                if (isset($property[$field])) {
                    $property[$field] = $rewrite($property[$field]);
                }
            }
        }

        unset($property);
    }

    /**
     * A class map and its queue map with each key the branches type with different classes moved onto its queue: every
     * branch's class there in branch order, each kept for its import under its own FQCN, as an embedded class is.
     *
     * @param  ClassMapType  $classes
     * @param  FqcnQueueMap  $queues
     * @param  list<array{ClassMapType, FqcnQueueMap}>  $branches
     * @return array{ClassMapType, FqcnQueueMap}
     */
    private static function queuedSplitClasses(array $classes, array $queues, array $branches): array
    {
        $byKey = [];

        foreach ($branches as [$branchClasses]) {
            foreach ($branchClasses as $name => $fqcn) {
                $byKey[$name][$fqcn] = true;
            }
        }

        foreach ($byKey as $name => $fqcns) {
            if (count($fqcns) < 2) {
                continue;
            }

            $queue = [];

            foreach ($branches as [$branchClasses, $branchQueues]) {
                if (isset($branchClasses[$name])) {
                    $queue[] = $branchClasses[$name];
                }

                array_push($queue, ...$branchQueues[$name] ?? []);
            }

            unset($classes[$name]);

            foreach (array_keys($fqcns) as $fqcn) {
                $classes[$fqcn] = $fqcn;
            }

            $queues[$name] = $queue;
        }

        return [$classes, $queues];
    }

    /**
     * A queue map without one key's queue, where the other map queues the same classes for that key.
     *
     * @param  FqcnQueueMap  $queues
     * @param  FqcnQueueMap  $other
     * @return FqcnQueueMap
     */
    private static function withoutRepeat(array $queues, array $other, int|string $name): array
    {
        if (isset($queues[$name]) && $queues[$name] === ($other[$name] ?? null)) {
            unset($queues[$name]);
        }

        return $queues;
    }

    /**
     * Every class the given single-entry and list channels hold, once each.
     *
     * @param  list<ClassMapType>  $single
     * @param  list<FqcnQueueMap>  $listed
     * @return list<class-string>
     */
    private function fqcnsOnChannels(array $single, array $listed): array
    {
        $fqcns = array_merge([], ...array_map(array_values(...), $single));

        foreach ($listed as $map) {
            $fqcns = [...$fqcns, ...array_merge([], ...array_values($map))];
        }

        return array_values(array_unique($fqcns));
    }

    /**
     * The classes a property's single-entry channel and its list channel of one kind hold, in that order.
     *
     * @param  ClassMapType  $single
     * @param  FqcnQueueMap  $listed
     * @return list<class-string>
     */
    private function channelClasses(string $name, array $single, array $listed): array
    {
        return [...(isset($single[$name]) ? [$single[$name]] : []), ...$listed[$name] ?? []];
    }

    /**
     * List in branch order the enums the branches wrap a key in where they wrap it in different ones, as a ternary of
     * EnumResource arms is listed, when no branch reads it bare and its type holds one token per enum.
     *
     * @param  list<self>  $branches
     */
    private function listSplitEnumResources(array $branches): void
    {
        $wrapping = array_replace([], ...array_map(
            fn (self $branch): array => $branch->enumResources + $branch->multiEnumResourceFqcns,
            $branches,
        ));

        foreach ($this->properties as $property) {
            if (! isset($wrapping[$property['name']])) {
                continue;
            }

            $wrapped = $this->wrappedEnums($property['name'], $branches);
            $tokens = ValueResult::filledArms($property['type']);

            if ($wrapped === null || count($wrapped) < 2 || count($tokens) !== count($wrapped)) {
                continue;
            }

            unset($this->enumResources[$property['name']], $this->enumResourceArmShapes[$property['name']]);
            $this->multiEnumResourceFqcns[$property['name']] = $wrapped;
        }
    }

    /**
     * Each enum the branches holding a key wrap it in, once each in branch order; null where one of them does not wrap
     * it, or also reads it bare.
     *
     * @param  list<self>  $branches
     * @return list<class-string>|null
     */
    private function wrappedEnums(string $name, array $branches): ?array
    {
        $wrapped = [];

        foreach ($branches as $branch) {
            if (! in_array($name, array_column($branch->properties, 'name'), true)) {
                continue;
            }

            $own = $branch->multiEnumResourceFqcns[$name]
                ?? (isset($branch->enumResources[$name]) ? [$branch->enumResources[$name]] : null);

            if ($own === null || isset($branch->directEnumFqcns[$name])) {
                return null;
            }

            array_push($wrapped, ...$own);
        }

        return array_values(array_unique($wrapped));
    }
}
