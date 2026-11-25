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
 * What an array shape holds beside its keys: the custom imports and carried classes its values spell, the three read
 * flags, the flat type alias a collection publishes, the variants its fold read, the spreads a nested literal prints
 * as arms, and each channel map's insertion order with the entries no key of the shape owns, such as the classes an
 * embedded queue keys by themselves.
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
}
