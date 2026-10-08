<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Order;
use Workbench\App\Services\ChannelDefaults;

/**
 * Regression fixture for Task 17A: SomeClass::CONSTANT as a property value resolves to a real TS
 * type via reflection instead of unknown. Also pins that `Foo::class` and an enum case reached
 * through EnumResource::make() keep resolving exactly as before alongside the new feature.
 *
 * @mixin Order
 */
class ClassConstantResource extends AbstractVersionedResource
{
    protected const int SCHEMA_VERSION = 2;

    protected const Status DEFAULT_STATUS = Status::Draft;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Array constant with a nested array, two levels deep.
            'owner_minimum_channels' => ChannelDefaults::DEFAULT_CHANNELS,
            // Scalar constant (int).
            'max_retries' => ChannelDefaults::MAX_RETRIES,
            // self::CONSTANT referenced from within the resource.
            'schema_version' => self::SCHEMA_VERSION,
            // parent::CONSTANT referenced from AbstractVersionedResource.
            'base_version' => parent::BASE_VERSION,
            // A constant whose own value is another class's enum case.
            'default_status' => self::DEFAULT_STATUS,
            // Reached through ?? — the left arm is an undefined model attribute (unknown).
            'fallback_channels' => $this->totally_unmapped_field ?? ChannelDefaults::DEFAULT_CHANNELS,
            // A plain list where every element agrees — resolves to string[].
            'channel_tags' => ChannelDefaults::CHANNEL_TAGS,
            // A list whose elements don't agree — resolves to a union element array.
            'mixed_tags' => ChannelDefaults::MIXED_TAGS,
            // A list nested inside a keyed constant — each value must resolve to string[], not
            // the Record<string, unknown> a keyless item would misreport through the AST pipeline.
            'nested_tags' => ChannelDefaults::NESTED_TAGS,
            // Negative case: references an undefined constant, degrading to unknown at read time
            // rather than aborting the whole generation run.
            'broken_channels' => ChannelDefaults::BROKEN,
            // Negative case: one element past MAX_CONSTANT_ARRAY_ELEMENTS.
            'over_element_limit' => ChannelDefaults::OVER_ELEMENT_LIMIT,
            // Negative case: one level past MAX_CONSTANT_ARRAY_DEPTH.
            'over_depth_limit' => ChannelDefaults::OVER_DEPTH_LIMIT,
            // An enum case nested inside a keyed constant — the import must survive embedding.
            'status_map' => ChannelDefaults::STATUS_MAP,
            // An enum case nested inside a list constant — same requirement, list shape.
            'status_list' => ChannelDefaults::STATUS_LIST,
            // All-int, non-sequential keys — every member is dropped, not a regression (see
            // ChannelDefaults::ALL_INT_KEYS).
            'all_int_keys' => ChannelDefaults::ALL_INT_KEYS,
            // A mixed string/int-keyed constant — the int-keyed member is silently dropped.
            'mixed_keys' => ChannelDefaults::MIXED_KEYS,
            // New behaviour: `Foo::class` now types as a plain string, not a resource/model type —
            // the four risky call sites (EnumResource::make(), toResource(...), #[Collects], the
            // $collects default) are guarded separately by their own untouched fixtures.
            'resource_marker' => self::class,
            // Unaffected: an enum case reached through EnumResource::make() — a separate,
            // pre-existing path this feature does not touch.
            'status_marker' => EnumResource::make(Status::Draft),
        ];
    }
}
