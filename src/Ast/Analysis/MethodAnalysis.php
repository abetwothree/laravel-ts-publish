<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DispatchesFqcnResults;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumNames;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ClassIdentity;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\TokenKind;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\LiteralVariants;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TopLevelRenumbering;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\RedundantArms;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Holds the result of AST analysis of a class method returning an array (e.g. a resource's toArray()).
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type HeldNulls from ExpressionHandler
 * @phpstan-import-type Slots from TokenSlots
 * @phpstan-import-type InlineSpreadArm from InlineObjectResult
 * @phpstan-import-type ObjectSpreadArm from InlineObjectResult
 * @phpstan-import-type AbsorbRules from EnumValueTypes
 * @phpstan-import-type TokenSpelling from ClassTokenQueue
 *
 * @phpstan-type ClassMapType = array<array-key, class-string>
 * @phpstan-type ImportMapType = TypesImportMap
 * @phpstan-type InlineEnumFqcnsMap = array<array-key, list<class-string>>
 * @phpstan-type InlineModelFqcnsMap = array<array-key, list<class-string>>
 * @phpstan-type InlineResourceFqcnsMap = array<array-key, list<class-string>>
 * @phpstan-type WrappedEnumsMap = array<array-key, list<class-string>>
 * @phpstan-type FqcnQueueMap = array<array-key, list<class-string>>
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
 *     splitOf?: string,
 *     absent?: true,
 *     removed?: true,
 * }
 * @phpstan-type AnalyzedPropertyList = list<AnalyzedProperty>
 * @phpstan-type PresenceFacts = array{absent?: true, removed?: true}
 *
 * A row's `optional` is its printed `?`. Two facts of the body say why: `absent`, the key is missing on some path,
 * where a value held before it survives; `removed`, its value may be removed, as a `when()` MissingValue or an Inertia
 * `optional()` prop is, which no held value survives. A `?` a cast or a docblock puts on a row sets neither.
 *
 * addProperty() reads its `$optional` as absent, and a value's own `optional` as removed unless the value is `absent`
 * without `removed`.
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
     * spread of a value that is no closed shape. In the gap mode an inline object of them carries the open marker, so
     * the merge keeps a key only its docblock declares.
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
     * Property name => the model or resource behind each token of a name a model and a resource both print in its
     * value, in print order: the value's `alikeClassFqcns`, which ClassIdentity::queueByToken() reads beside the
     * key's channels where they still line up.
     *
     * @var FqcnQueueMap
     */
    public array $alikeClassFqcns = [];

    /**
     * Property name => the nulls of a value addProperty() last gave the key that PHP holds as an object on some arm
     * (ValueResult::heldNullsOf()), which a returned variable's walk reads where the write lands. A merge or a join
     * sets none, so its keys read as plain.
     *
     * @var array<array-key, HeldNulls>
     */
    public array $heldObjectKeys = [];

    /**
     * Property name => true where the value addProperty() last gave the key is an object Inertia replaces before a
     * dotted key writes under it (ValueResult::closedToDottedKeys()). A merge takes the source's; a join keeps a key
     * every branch holding it closes. A fact of the value, so forgetChannels(), which a cast also runs, keeps it.
     *
     * @var array<array-key, true>
     */
    public array $closedToDottedKeys = [];

    /**
     * The groups of keys that follow a sibling key's value, as the literal's fold read them (VariantReader). A merge
     * or a join of two analyses drops them; a join of one keeps them, and each group says whether its rows still hold.
     */
    public ?LiteralVariants $variants = null;

    /**
     * The spreads a nested literal's print intersects with its keys, in item order, as its fold left them. They print
     * beside that literal's own keys alone, so no merge, join or slice carries them.
     *
     * @var list<InlineSpreadArm|ObjectSpreadArm>
     */
    private array $spreadArms = [];

    /**
     * @param  AnalyzedPropertyList  $properties  `bodyType` is set only on an index signature whose value a docblock
     *                                            fill or a same-pattern union changed: the value its body gives it;
     *                                            `fillType` is that signature's docblock fill, kept through a put-back
     * @param  ClassMapType  $nestedResources  property name => resource FQCN
     * @param  ImportMapType  $customImports  import path => list of type names
     * @param  ClassMapType  $directEnumFqcns  property name => FQCN for direct access; FQCN => FQCN for embedded enums
     * @param  ClassMapType  $modelFqcns  property name => model FQCN (from bare whenLoaded)
     * @param  InlineEnumFqcnsMap  $inlineEnumFqcns  property name => list of enum FQCNs embedded in inline object type strings
     * @param  InlineModelFqcnsMap  $inlineModelFqcns  property name => list of model FQCNs embedded in inline object type strings
     * @param  InlineResourceFqcnsMap  $inlineResourceFqcns  property name => the resource FQCN behind each resource
     *                                                       token, in type order
     * @param  InlineEnumFqcnsMap  $inlineEnumResourceFqcns  property name => the enum behind each const a wrap's
     *                                                       `AsEnum<typeof …>` spells, one per token in print order
     * @param  WrappedEnumsMap  $wrappedEnums  property name => the enums its value wraps in an `EnumResource`, at any
     *                                         depth; a set no token is read from
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
        public array $nestedResources = [],
        public array $customImports = [],
        public array $directEnumFqcns = [],
        public array $modelFqcns = [],
        public array $inlineEnumFqcns = [],
        public array $inlineModelFqcns = [],
        public array $inlineResourceFqcns = [],
        public array $inlineEnumResourceFqcns = [],
        public array $wrappedEnums = [],
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
        return self::channelKind($fqcn) !== null;
    }

    /**
     * The kind of inline channel a class is queued on: an enum's, a model's or an API resource's; null for any other.
     *
     * @return 'enums'|'models'|'resources'|null
     */
    public static function channelKind(string $fqcn): ?string
    {
        return TokenKind::ofClass($fqcn)?->value;
    }

    /**
     * A row with its printed `?` and its two presence facts set.
     *
     * @param  AnalyzedProperty  $property
     * @return AnalyzedProperty
     */
    public static function withPresence(array $property, bool $optional, bool $absent, bool $removed): array
    {
        unset($property['absent'], $property['removed']);

        return [...$property, 'optional' => $optional, ...self::presenceFacts($absent, $removed)];
    }

    /**
     * The presence facts a row keeps: each only where true.
     *
     * @return PresenceFacts
     */
    public static function presenceFacts(bool $absent, bool $removed): array
    {
        return [...($absent ? ['absent' => true] : []), ...($removed ? ['removed' => true] : [])];
    }

    /**
     * Whether a row still prints its literal one object per variant: its type is still the union it was split as.
     *
     * @param  AnalyzedProperty  $property
     */
    public static function isSplit(array $property): bool
    {
        return ($property['splitOf'] ?? null) === $property['type'];
    }

    /**
     * Record one resolved value as a property and route every FQCN channel it carries.
     *
     * The one place a value becomes a property: a channel added here reaches every collector,
     * where a hand-rolled collector had to be found and updated one at a time.
     *
     * Its presence facts read as the class docblock says.
     *
     * @param  ValueExpressionResult  $result
     */
    public function addProperty(string $name, array $result, bool $optional = false, string $description = ''): void
    {
        $absent = $result['absent'] ?? false;

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
            ...(ValueResult::isSplit($result) ? ['splitOf' => $result['type']] : []),
            ...self::presenceFacts(
                $optional || ($result['optional'] && $absent),
                $result['optional'] && (! $absent || ($result['removed'] ?? false)),
            ),
        ];

        $held = ValueResult::heldNullsOf($result);

        if ($held === null) {
            unset($this->heldObjectKeys[$name]);
        } else {
            $this->heldObjectKeys[$name] = $held;
        }

        if (ValueResult::closedToDottedKeys($result)) {
            $this->closedToDottedKeys[$name] = true;
        } else {
            unset($this->closedToDottedKeys[$name]);
        }

        $this->queueChannels($name, $result);

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
     * `properties` appends: each caller hands keys this analysis does not hold, as a literal's fold writes each key
     * once; a signature entry is one more runtime key.
     *
     * The class maps, `casts`, `declaredTokenFqcns` and `closedToDottedKeys` replace-merge, the source winning, a key
     * it sets with none losing its own; the inline maps append undeduped, as read by position.
     *
     * `carried` unions; `readsCompletely` ANDs, the `holds…Keys` flags OR; `variants` goes, as no fold read the whole.
     */
    public function merge(self $source): void
    {
        if (! $this->holdsNoneOf($source)) {
            throw new LogicException('merge() takes only keys the analysis does not hold; a literal\'s fold writes each once.');
        }

        $this->properties = [...$this->properties, ...$source->properties];
        $this->variants = null;
        $this->readsCompletely = $this->readsCompletely && $source->readsCompletely;
        $this->holdsUnreadKeys = $this->holdsUnreadKeys || $source->holdsUnreadKeys;
        $this->holdsUnnamedKeys = $this->holdsUnnamedKeys || $source->holdsUnnamedKeys;
        // A spread would renumber a numeric property name, which PHP stores as an int.
        $this->nestedResources = array_replace($this->nestedResources, $source->nestedResources);
        $this->directEnumFqcns = array_replace($this->directEnumFqcns, $source->directEnumFqcns);
        $this->modelFqcns = array_replace($this->modelFqcns, $source->modelFqcns);

        foreach ($source->properties as $property) {
            if (! isset($source->casts[$property['name']])) {
                unset($this->casts[$property['name']]);
            }

            if (! isset($source->declaredTokenFqcns[$property['name']])) {
                unset($this->declaredTokenFqcns[$property['name']]);
            }

            if (! isset($source->alikeClassFqcns[$property['name']])) {
                unset($this->alikeClassFqcns[$property['name']]);
            }

            unset($this->closedToDottedKeys[$property['name']]);
        }

        // A spread would renumber a numeric cast key.
        $this->casts = array_replace($this->casts, $source->casts);
        $this->declaredTokenFqcns = array_replace($this->declaredTokenFqcns, $source->declaredTokenFqcns);
        $this->alikeClassFqcns = array_replace($this->alikeClassFqcns, $source->alikeClassFqcns);
        $this->closedToDottedKeys = array_replace($this->closedToDottedKeys, $source->closedToDottedKeys);
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

        foreach ($source->wrappedEnums as $propName => $fqcns) {
            $this->wrappedEnums[$propName] = [...($this->wrappedEnums[$propName] ?? []), ...$fqcns];
        }
    }

    /**
     * Take over every key, channel, cast and flag of another analysis, as a returned variable's walk does where
     * exclusive branches join. A nested literal's spread arms stay behind, as they print beside that literal alone.
     */
    public function replaceWith(self $source): void
    {
        $this->properties = $source->properties;
        $this->readsCompletely = $source->readsCompletely;
        $this->holdsUnreadKeys = $source->holdsUnreadKeys;
        $this->holdsUnnamedKeys = $source->holdsUnnamedKeys;
        $this->nestedResources = $source->nestedResources;
        $this->customImports = $source->customImports;
        $this->directEnumFqcns = $source->directEnumFqcns;
        $this->modelFqcns = $source->modelFqcns;
        $this->inlineEnumFqcns = $source->inlineEnumFqcns;
        $this->inlineModelFqcns = $source->inlineModelFqcns;
        $this->inlineResourceFqcns = $source->inlineResourceFqcns;
        $this->inlineEnumResourceFqcns = $source->inlineEnumResourceFqcns;
        $this->wrappedEnums = $source->wrappedEnums;
        $this->casts = $source->casts;
        $this->carried = $source->carried;
        $this->declaredTokenFqcns = $source->declaredTokenFqcns;
        $this->alikeClassFqcns = $source->alikeClassFqcns;
        $this->heldObjectKeys = $source->heldObjectKeys;
        $this->closedToDottedKeys = $source->closedToDottedKeys;
        $this->flatTypeAlias = $source->flatTypeAlias;
        $this->flatTypeAliasFqcn = $source->flatTypeAliasFqcn;
        $this->variants = $source->variants;
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
            $this->wrappedEnums = self::withoutRepeat($this->wrappedEnums, $other->wrappedEnums, $name);
        }

        foreach ($this->customImports as $path => $types) {
            $this->customImports[$path] = array_values(array_diff($types, $other->customImports[$path] ?? []));
        }
    }

    /**
     * Queue, in branch order, the class each branch of a merge types a key with where two branches type it apart, as a
     * union's arms are: merge() keeps only the last branch's, leaving the others' tokens with no import.
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
            $kind = self::channelKind($fqcn);

            if ($kind === 'enums') {
                $this->inlineEnumFqcns[$name][] = $fqcn;
                $this->directEnumFqcns[$fqcn] = $fqcn;
            } elseif ($kind === 'models') {
                $this->inlineModelFqcns[$name][] = $fqcn;
                $this->modelFqcns[$fqcn] = $fqcn;
            } elseif ($kind === 'resources') {
                $this->inlineResourceFqcns[$name][] = $fqcn;
                $this->nestedResources[$fqcn] = $fqcn;
            }
        }
    }

    /**
     * Replace a key's inline queues with classes its text spells inside its value, queued as queueEmbeddedClasses()
     * queues them.
     *
     * @param  list<class-string>  $inline
     */
    public function requeueInline(string $name, array $inline): void
    {
        unset(
            $this->inlineEnumFqcns[$name], $this->inlineModelFqcns[$name], $this->inlineResourceFqcns[$name],
            $this->alikeClassFqcns[$name],
        );
        $this->queueEmbeddedClasses($name, $inline);
    }

    /**
     * Replace a key's import channels with the ones a value of it carries, as addProperty() routes them, its cast kept.
     *
     * @param  ValueExpressionResult  $result
     */
    public function requeueChannels(string $name, array $result): void
    {
        unset(
            $this->nestedResources[$name], $this->directEnumFqcns[$name], $this->modelFqcns[$name],
            $this->inlineEnumFqcns[$name], $this->inlineModelFqcns[$name], $this->inlineResourceFqcns[$name],
            $this->inlineEnumResourceFqcns[$name], $this->wrappedEnums[$name],
        );
        $this->queueChannels($name, $result);
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
     * Each key once, as PHP holds it after every write: its last value, where its first write stood; a signature keeps
     * every entry, each one more runtime key it covers.
     *
     * @return AnalyzedPropertyList
     */
    public function writtenProperties(): array
    {
        $written = [];

        foreach ($this->properties as $index => $property) {
            $written[JsEmitter::isIndexSignatureKey($property['name']) ? '#'.$index : '.'.$property['name']] = $property;
        }

        return array_values($written);
    }

    /**
     * The spreads a nested literal's print intersects with its keys, in item order.
     *
     * @return list<InlineSpreadArm|ObjectSpreadArm>
     */
    public function spreadArms(): array
    {
        return $this->spreadArms;
    }

    /**
     * Keep the spreads a nested literal's print intersects with its keys, as LiteralWrites::applyTo() hands them on.
     *
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $arms
     */
    public function setSpreadArms(array $arms): void
    {
        $this->spreadArms = $arms;
    }

    /**
     * Whether any FQCN channel carries an entry for this property name. A direct enum's entry may name an enum whose
     * override the value spells instead of its `…Type`; channelNames() leaves such an enum out.
     */
    public function hasFqcnChannel(string $name): bool
    {
        return isset($this->nestedResources[$name]) || isset($this->directEnumFqcns[$name])
            || isset($this->modelFqcns[$name]) || isset($this->inlineEnumFqcns[$name])
            || isset($this->inlineModelFqcns[$name]) || isset($this->inlineResourceFqcns[$name])
            || isset($this->inlineEnumResourceFqcns[$name]) || isset($this->wrappedEnums[$name]);
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
            $this->readEnumFqcns($name),
            static fn (string $enum): bool => LaravelTsPublish::retypedEnumType($enum) === null,
        );
        $names = array_column($this->classSpellings($name), 1);

        foreach ([...$held, ...$this->wrappedEnumFqcns($name)] as $enum) {
            array_push($names, EnumNames::constName($enum), ...EnumNames::names($enum));
        }

        return array_values(array_unique($names));
    }

    /**
     * The enums, models and resources behind a property's type tokens: the enums in the order their channels queue
     * them, then the models and resources as classFqcns() lists them.
     *
     * @return list<class-string>
     */
    public function channelFqcns(string $name): array
    {
        return [...$this->readEnumFqcns($name), ...$this->classFqcns($name)];
    }

    /**
     * The models and resources behind a property's type tokens as one queue, each name's classes in the order its
     * tokens print, a name a model and a resource both print included (ClassIdentity::queueByToken()).
     *
     * @return list<class-string>
     */
    public function classFqcns(string $name): array
    {
        return ClassIdentity::queueByToken([
            ...$this->channelClasses($name, $this->modelFqcns, $this->inlineModelFqcns),
            ...$this->channelClasses($name, $this->nestedResources, $this->inlineResourceFqcns),
        ], $this->alikeClassFqcns[$name] ?? []);
    }

    /**
     * The classes behind a property's type tokens that no inline queue holds, in channelFqcns() order.
     *
     * @return list<class-string>
     */
    public function singleChannelFqcns(string $name): array
    {
        return [
            ...(isset($this->directEnumFqcns[$name]) ? [$this->directEnumFqcns[$name]] : []),
            ...(isset($this->modelFqcns[$name]) ? [$this->modelFqcns[$name]] : []),
            ...(isset($this->nestedResources[$name]) ? [$this->nestedResources[$name]] : []),
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
            [$this->directEnumFqcns, $this->modelFqcns, $this->nestedResources],
            [$this->inlineEnumFqcns, $this->inlineModelFqcns, $this->inlineResourceFqcns],
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
     * Every enum any key's value wraps in an `EnumResource`, once each: the keys' wrappedEnumFqcns() together.
     *
     * @return list<class-string>
     */
    public function allWrappedEnumFqcns(): array
    {
        return $this->fqcnsOnChannels([], [$this->wrappedEnums]);
    }

    /**
     * Every enum whose const any key's `AsEnum<typeof …>` spells, once each.
     *
     * @return list<class-string>
     */
    public function allWrapConstFqcns(): array
    {
        return $this->fqcnsOnChannels([], [$this->inlineEnumResourceFqcns]);
    }

    /**
     * The enums a property's value reads directly, whose aliases its type may spell.
     *
     * @return list<class-string>
     */
    public function readEnumFqcns(string $name): array
    {
        return $this->channelClasses($name, $this->directEnumFqcns, $this->inlineEnumFqcns);
    }

    /**
     * The enums a property's value wraps in an `EnumResource`, at any depth, once each: what a key wraps, never which
     * const a token names.
     *
     * @return list<class-string>
     */
    public function wrappedEnumFqcns(string $name): array
    {
        return array_values(array_unique($this->wrappedEnums[$name] ?? []));
    }

    /**
     * The enum behind each const an `AsEnum<typeof …>` of a property's type spells, one per token in print order.
     *
     * @return list<class-string>
     */
    public function wrapConstFqcns(string $name): array
    {
        return $this->inlineEnumResourceFqcns[$name] ?? [];
    }

    /**
     * Forget every import channel entry and cast entry keyed by this property name, when another value takes the key
     * over: an import channel entry left behind would alias the new type by the old one's classes or keep an import
     * nothing spells, and a cast entry would fit the new value to a text the key no longer publishes.
     */
    public function forgetChannels(string $name): void
    {
        unset(
            $this->nestedResources[$name], $this->directEnumFqcns[$name], $this->modelFqcns[$name],
            $this->inlineEnumFqcns[$name], $this->inlineModelFqcns[$name], $this->inlineResourceFqcns[$name],
            $this->inlineEnumResourceFqcns[$name], $this->wrappedEnums[$name], $this->casts[$name],
            $this->declaredTokenFqcns[$name], $this->alikeClassFqcns[$name],
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

        $this->forgetChannels($name);

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
     * Drop each named key another analysis already holds; signature entries stay.
     *
     * PHP's `+` and Laravel's mergeData() keep a key already set, while a signature entry is one more runtime key.
     */
    public function dropKeysHeldBy(self $held): void
    {
        $this->dropKeys(array_column($held->properties, 'name'));
    }

    /**
     * Drop each named key's rows with every map renameKeys() rekeys under its name; signature entries stay.
     *
     * @param  list<string>  $names
     */
    public function dropKeys(array $names): void
    {
        $names = array_flip($names);
        $kept = [];

        foreach ($this->properties as $property) {
            $name = $property['name'];

            if (! isset($names[$name]) || JsEmitter::isIndexSignatureKey($name)) {
                $kept[] = $property;

                continue;
            }

            $this->forgetChannels($name);
            unset($this->heldObjectKeys[$name], $this->closedToDottedKeys[$name]);
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

        $this->nestedResources = TopLevelRenumbering::rekey($this->nestedResources, $names);
        $this->directEnumFqcns = TopLevelRenumbering::rekey($this->directEnumFqcns, $names);
        $this->modelFqcns = TopLevelRenumbering::rekey($this->modelFqcns, $names);
        $this->inlineEnumFqcns = TopLevelRenumbering::rekey($this->inlineEnumFqcns, $names);
        $this->inlineModelFqcns = TopLevelRenumbering::rekey($this->inlineModelFqcns, $names);
        $this->inlineResourceFqcns = TopLevelRenumbering::rekey($this->inlineResourceFqcns, $names);
        $this->inlineEnumResourceFqcns = TopLevelRenumbering::rekey($this->inlineEnumResourceFqcns, $names);
        $this->wrappedEnums = TopLevelRenumbering::rekey($this->wrappedEnums, $names);
        $this->casts = TopLevelRenumbering::rekey($this->casts, $names);
        $this->declaredTokenFqcns = TopLevelRenumbering::rekey($this->declaredTokenFqcns, $names);
        $this->alikeClassFqcns = TopLevelRenumbering::rekey($this->alikeClassFqcns, $names);
        $this->heldObjectKeys = TopLevelRenumbering::rekey($this->heldObjectKeys, $names);
        $this->closedToDottedKeys = TopLevelRenumbering::rekey($this->closedToDottedKeys, $names);
    }

    /**
     * One key's row and every map renameKeys() rekeys under its name, with each class its queues key by itself: what
     * LiteralWrites moves when it writes the key once. A signature keeps every row it holds.
     */
    public function sliceOf(string $name): self
    {
        $slice = new self;
        $rows = array_values(array_filter($this->properties, static fn (array $property): bool => $property['name'] === $name));
        $slice->properties = JsEmitter::isIndexSignatureKey($name) ? $rows : array_slice($rows, -1);
        $key = [$name => true];
        $slice->nestedResources = array_intersect_key($this->nestedResources, $key);
        $slice->directEnumFqcns = array_intersect_key($this->directEnumFqcns, $key);
        $slice->modelFqcns = array_intersect_key($this->modelFqcns, $key);
        $slice->inlineEnumFqcns = array_intersect_key($this->inlineEnumFqcns, $key);
        $slice->inlineModelFqcns = array_intersect_key($this->inlineModelFqcns, $key);
        $slice->inlineResourceFqcns = array_intersect_key($this->inlineResourceFqcns, $key);
        $slice->inlineEnumResourceFqcns = array_intersect_key($this->inlineEnumResourceFqcns, $key);
        $slice->wrappedEnums = array_intersect_key($this->wrappedEnums, $key);
        $slice->casts = array_intersect_key($this->casts, $key);
        $slice->declaredTokenFqcns = array_intersect_key($this->declaredTokenFqcns, $key);
        $slice->alikeClassFqcns = array_intersect_key($this->alikeClassFqcns, $key);
        $slice->heldObjectKeys = array_intersect_key($this->heldObjectKeys, $key);
        $slice->closedToDottedKeys = array_intersect_key($this->closedToDottedKeys, $key);

        foreach ($slice->inlineEnumFqcns[$name] ?? [] as $fqcn) {
            $slice->directEnumFqcns += array_intersect_key($this->directEnumFqcns, [$fqcn => true]);
        }

        foreach ($slice->inlineModelFqcns[$name] ?? [] as $fqcn) {
            $slice->modelFqcns += array_intersect_key($this->modelFqcns, [$fqcn => true]);
        }

        foreach ($slice->inlineResourceFqcns[$name] ?? [] as $fqcn) {
            $slice->nestedResources += array_intersect_key($this->nestedResources, [$fqcn => true]);
        }

        return $slice;
    }

    /**
     * Append a slice's rows and maps, as LiteralWrites writes a key once into an analysis holding none of it; a
     * slice's held objects come too only where $keepsHeldObjects, as addProperty() keeps them and merge() does not.
     */
    public function appendSlice(self $slice, bool $keepsHeldObjects): void
    {
        $this->merge($slice);

        if ($keepsHeldObjects) {
            $this->heldObjectKeys = array_replace($this->heldObjectKeys, $slice->heldObjectKeys);
        }
    }

    /**
     * Rewrite every property's type, body type and fill, except a key a cast types and a value that keeps its keys. A
     * type's queues keep to the tokens its new text spells, so a token after an arm the rewrite drops keeps its class.
     *
     * @param  Closure(string): string  $rewrite
     */
    public function rewritePropertyTypes(Closure $rewrite): void
    {
        // An internal function's callable, such as `strtolower(...)`, throws on the row rewriteTypes() also passes.
        $text = static fn (string $type): string => $rewrite($type);
        $rows = array_count_values(array_column($this->properties, 'name'));

        $this->rewriteTypes(
            $text,
            fn (array $property): bool => ! isset($this->casts[$property['name']]) && ! ($property['keepsKeys'] ?? false),
            fn (string $type, array $property): string => $rows[$property['name']] === 1
                ? $this->retypedByClass($property['name'], $type, $text)
                : $text($type),
        );
    }

    /**
     * Turn each declared-arm mark in the type, body type and fill of every property back into what it stands for
     * (VisibleGaps::unmarked()), its gaps and open markers left in place.
     */
    public function unmarkDeclaredArms(): void
    {
        $this->rewriteTypes(VisibleGaps::unmarked(...), static fn (): bool => true);
    }

    /**
     * Take every gap arm and open marker out of the type, body type and fill of each property $strips takes, every one
     * where it is null, and raise the warnings of each gap taken out. Each mark is turned back too, except in the
     * declaration mode, where the declaration the read is under replaces it (DeclaredArms::underDeclaration()).
     *
     * @param  (Closure(string): bool)|null  $strips  whether to strip a property, by its name
     */
    public function stripGaps(?Closure $strips = null): void
    {
        $ids = [];
        $keepsMarks = resolve(AnalysisMemo::class)->marksDeclared();
        $this->rewriteTypes(
            static function (string $type) use (&$ids, $keepsMarks): string {
                [$stripped, $gaps] = VisibleGaps::stripped($type, $keepsMarks);
                array_push($ids, ...$gaps);

                return $stripped;
            },
            static fn (array $property): bool => $strips === null || $strips($property['name']),
        );

        DroppedUnionArms::stripped(array_values(array_unique($ids)));
    }

    /**
     * Leave out every union arm a sibling admits from each key's type, body type and fill, except a key a cast types:
     * `never[]` beside a list or `[]`, and a case literal beside the alias of an enum it reads. Alike arms then keep
     * one, unless a name on the key spells two classes, a slot is declared or `$queuesLater` holds.
     *
     * @param  (Closure(string): bool)|null  $queuesLater  whether the caller still queues classes on a key, by its name
     */
    public function absorbRedundantArms(?Closure $queuesLater = null): void
    {
        /** @var array<array-key, AbsorbRules> $rulesByKey */
        $rulesByKey = [];

        $this->rewriteTypes(
            function (string $type, array $property) use (&$rulesByKey, $queuesLater): string {
                $name = $property['name'];
                [$admitted, $dedupesAlike] = $rulesByKey[$name] ??= $this->absorbRules($name, $queuesLater);

                return RedundantArms::absorbed($type, $admitted, $dedupesAlike);
            },
            fn (array $property): bool => ! isset($this->casts[$property['name']]),
        );
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
     * Set a key's queue on a map, or clear it where the queue is empty.
     *
     * @param  FqcnQueueMap  $map
     * @param  list<class-string>  $fqcns
     */
    private static function setQueue(array &$map, string $name, array $fqcns): void
    {
        if ($fqcns === []) {
            unset($map[$name]);
        } else {
            $map[$name] = $fqcns;
        }
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
     * A property's rules for leaving out redundant arms, read off its enum, model and resource channels; a declaration
     * slotting its tokens, an override's among them, keeps its token queue.
     *
     * @param  (Closure(string): bool)|null  $queuesLater  whether the caller still queues classes on a key, by its name
     * @return AbsorbRules
     */
    private function absorbRules(string $name, ?Closure $queuesLater): array
    {
        $classes = $this->classSpellings($name);

        return EnumValueTypes::absorbRules(
            read: $this->readEnumFqcns($name),
            wrapped: $this->wrappedEnumFqcns($name),
            takenNames: array_column($classes, 1),
            classes: $classes,
            keepsTokens: ($this->declaredTokenFqcns[$name] ?? []) !== [],
            queuesLater: $queuesLater === null ? null : static fn (): bool => $queuesLater($name),
        );
    }

    /**
     * Each model and then each resource on a property's channels, beside the name it prints.
     *
     * @return list<TokenSpelling>
     */
    private function classSpellings(string $name): array
    {
        return [
            ...array_map(
                static fn (string $model): array => [$model, class_basename($model)],
                $this->channelClasses($name, $this->modelFqcns, $this->inlineModelFqcns),
            ),
            ...array_map(
                static fn (string $resource): array => [$resource, TsNaming::resourceTypeName($resource)],
                $this->channelClasses($name, $this->nestedResources, $this->inlineResourceFqcns),
            ),
        ];
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
     * Route every import channel a key's value carries onto the key, beside the record of its alike tokens.
     *
     * @param  ValueExpressionResult  $result
     */
    private function queueChannels(string $name, array $result): void
    {
        $this->dispatchFqcnResults(
            $name, $result, $this->directEnumFqcns, $this->nestedResources, $this->modelFqcns, $this->wrappedEnums,
        );

        if (($result['alikeClassFqcns'] ?? []) === []) {
            unset($this->alikeClassFqcns[$name]);
        } else {
            $this->alikeClassFqcns[$name] = $result['alikeClassFqcns'];
        }

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
    }

    /**
     * A key's type rewritten, its queues and its declared slots kept to the tokens the new text spells.
     *
     * @param  Closure(string): string  $rewrite
     */
    private function retypedByClass(string $name, string $type, Closure $rewrite): string
    {
        $slots = $this->declaredTokenFqcns[$name] ?? [];

        if ($slots === []) {
            return $this->requeuedRewrite($name, $type, $rewrite);
        }

        [$retyped, $slots] = TokenSlots::retyped(
            $type,
            $slots,
            fn (string $marked): string => $this->requeuedRewrite($name, $marked, $rewrite),
        );

        if ($slots === []) {
            unset($this->declaredTokenFqcns[$name]);
        } else {
            $this->declaredTokenFqcns[$name] = $slots;
        }

        return $retyped;
    }

    /**
     * A key's type rewritten, its queues kept to the classes behind the tokens the new text spells
     * (ValueResult::retypedByClass()).
     *
     * @param  Closure(string): string  $rewrite
     */
    private function requeuedRewrite(string $name, string $type, Closure $rewrite): string
    {
        if (! $this->hasFqcnChannel($name)) {
            return $rewrite($type);
        }

        $retyped = ValueResult::retypedByClass($this->tokenResult($name, $type), $rewrite)
            ?? $this->tokenResult($name, $rewrite($type));
        self::setQueue($this->inlineEnumFqcns, $name, $retyped['embeddedEnumFqcns'] ?? []);
        self::setQueue($this->inlineModelFqcns, $name, $retyped['embeddedModelFqcns'] ?? []);
        self::setQueue($this->inlineResourceFqcns, $name, $retyped['embeddedResourceFqcns'] ?? []);
        self::setQueue($this->inlineEnumResourceFqcns, $name, $retyped['embeddedEnumResourceFqcns'] ?? []);
        self::setQueue($this->alikeClassFqcns, $name, $retyped['alikeClassFqcns'] ?? []);

        return $retyped['type'];
    }

    /**
     * A key's type beside the classes its channels queue behind its tokens, as a value carries them.
     *
     * @return ValueExpressionResult
     */
    private function tokenResult(string $name, string $type): array
    {
        return [
            'type' => $type,
            'optional' => false,
            ...(isset($this->directEnumFqcns[$name]) ? ['directEnumFqcn' => $this->directEnumFqcns[$name]] : []),
            ...(isset($this->modelFqcns[$name]) ? ['modelFqcn' => $this->modelFqcns[$name]] : []),
            ...(isset($this->nestedResources[$name]) ? ['resourceFqcn' => $this->nestedResources[$name]] : []),
            'embeddedEnumFqcns' => $this->inlineEnumFqcns[$name] ?? [],
            'embeddedModelFqcns' => $this->inlineModelFqcns[$name] ?? [],
            'embeddedResourceFqcns' => $this->inlineResourceFqcns[$name] ?? [],
            'embeddedEnumResourceFqcns' => $this->inlineEnumResourceFqcns[$name] ?? [],
            'alikeClassFqcns' => $this->alikeClassFqcns[$name] ?? [],
        ];
    }

    /**
     * Whether another analysis names no key this one holds: the contract merge() checks; a signature may repeat.
     */
    private function holdsNoneOf(self $source): bool
    {
        $held = array_flip(array_column($this->properties, 'name'));

        return array_all($source->properties, static fn (array $property): bool => ! isset($held[$property['name']])
            || JsEmitter::isIndexSignatureKey($property['name']));
    }

    /**
     * Rewrite the type, body type and fill of each property $takes takes, the type through $retype where given.
     *
     * @param  Closure(string, AnalyzedProperty): string  $rewrite  each text, beside the row it belongs to
     * @param  Closure(AnalyzedProperty): bool  $takes
     * @param  (Closure(string, AnalyzedProperty): string)|null  $retype
     */
    private function rewriteTypes(Closure $rewrite, Closure $takes, ?Closure $retype = null): void
    {
        foreach ($this->properties as &$property) {
            if (! $takes($property)) {
                continue;
            }

            $property['type'] = ($retype ?? $rewrite)($property['type'], $property);

            foreach (['bodyType', 'fillType'] as $field) {
                if (isset($property[$field])) {
                    $property[$field] = $rewrite($property[$field], $property);
                }
            }
        }

        unset($property);
    }
}
