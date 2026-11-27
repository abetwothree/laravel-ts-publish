<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\LiteralVariants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an array shape holds beside its keys: the custom imports and carried classes its values spell, the read flags,
 * the flat type alias, the fold's variants, a nested literal's spread arms, and each channel map's insertion order with
 * the entries no key owns, such as the classes an embedded queue keys by themselves.
 *
 * @phpstan-import-type ImportMapType from MethodAnalysis
 * @phpstan-import-type CarriedMap from MethodAnalysis
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type FqcnQueueMap from MethodAnalysis
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type Slots from TokenSlots
 * @phpstan-import-type HeldNulls from ExpressionHandler
 * @phpstan-import-type InlineSpreadArm from InlineObjectResult
 * @phpstan-import-type ObjectSpreadArm from InlineObjectResult
 *
 * @phpstan-type LooseMaps = array{
 *     nestedResources?: ClassMapType,
 *     directEnumFqcns?: ClassMapType,
 *     modelFqcns?: ClassMapType,
 *     inlineEnumFqcns?: FqcnQueueMap,
 *     inlineModelFqcns?: FqcnQueueMap,
 *     inlineResourceFqcns?: FqcnQueueMap,
 *     inlineEnumResourceFqcns?: FqcnQueueMap,
 *     wrappedEnums?: FqcnQueueMap,
 *     casts?: CastMap,
 *     declaredTokenFqcns?: array<array-key, Slots>,
 *     alikeClassFqcns?: FqcnQueueMap,
 *     heldObjectKeys?: array<array-key, HeldNulls>,
 *     closedToDottedKeys?: array<array-key, true>,
 * }
 * @phpstan-type MapOrder = array<string, list<array-key>>
 *
 * @internal
 */
final readonly class ShapeWide
{
    /**
     * Hold what an array shape holds beside its keys.
     *
     * @param  ImportMapType  $customImports
     * @param  CarriedMap  $carried
     * @param  class-string<JsonResource|Model>|null  $flatTypeAliasFqcn
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $spreadArms  the spreads a nested literal's print intersects with
     *                                                             its keys, flattened as the analysis holds them
     * @param  MapOrder  $order  each channel map's keys in insertion order, by map name
     * @param  LooseMaps  $loose  each channel map's entries under a key no entry of the shape names
     */
    public function __construct(
        public array $customImports = [],
        public array $carried = [],
        public bool $readsCompletely = true,
        public bool $holdsUnreadKeys = false,
        public bool $holdsUnnamedKeys = false,
        public ?string $flatTypeAlias = null,
        public ?string $flatTypeAliasFqcn = null,
        public ?LiteralVariants $variants = null,
        public array $spreadArms = [],
        public array $order = [],
        public array $loose = [],
    ) {}

    /**
     * The same wide data with its read flags set where given.
     */
    public function flagged(?bool $readsCompletely = null, ?bool $holdsUnreadKeys = null): self
    {
        return new self(
            $this->customImports, $this->carried, $readsCompletely ?? $this->readsCompletely,
            $holdsUnreadKeys ?? $this->holdsUnreadKeys, $this->holdsUnnamedKeys, $this->flatTypeAlias,
            $this->flatTypeAliasFqcn, $this->variants, $this->spreadArms, $this->order, $this->loose,
        );
    }

    /**
     * The same wide data once a key is retyped with no class, as MethodAnalysis::retypeClassLess() retypes it: other
     * custom imports, and the key out of each map's order but its held objects' and its dotted-key close's.
     *
     * @param  ImportMapType  $customImports
     */
    public function retyping(string $name, array $customImports): self
    {
        $order = $this->order;

        foreach ($order as $map => $keys) {
            if ($map !== 'heldObjectKeys' && $map !== 'closedToDottedKeys') {
                $order[$map] = array_values(array_filter($keys, static fn (int|string $key): bool => (string) $key !== $name));
            }
        }

        return new self(
            $customImports, $this->carried, $this->readsCompletely, $this->holdsUnreadKeys, $this->holdsUnnamedKeys,
            $this->flatTypeAlias, $this->flatTypeAliasFqcn, $this->variants, $this->spreadArms, $order, $this->loose,
        );
    }
}
