<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\ValueObjects\Spreads\ArmShelf;

/**
 * Named keys and resource or model arms beside typed maps, before and after them (GH-161 D2, D3).
 *
 * `three_model_spreads` is a guard whose golden is develop's known-short reading until #80 l64: of two same-named
 * users it keeps one arm.
 *
 * @property-read ArmShelf $resource
 */
class ArmBesideMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $maybe = [];

        if ($this->resource->flag) {
            $maybe['name'] = 5;
        }

        $contact = $this->resource->contact;
        $owner = $this->resource->owner;
        $deputy = $this->resource->deputy;

        return [
            'map_before_unfit_key' => [...$this->resource->labels, 'a' => 1],
            'model_key_before_map' => ['user' => $this->resource->user, ...$this->resource->labels],
            'arm_before_map' => [
                ...TitleFlagArmResource::make($this->resource)->resolve($request),
                ...$this->resource->labels,
            ],
            'map_before_arm' => [
                ...$this->resource->labels,
                ...TitleFlagArmResource::make($this->resource)->resolve($request),
            ],
            'arm_then_two_maps' => [
                ...IdTitleArmResource::make($this->resource)->resolve($request),
                ...$this->resource->labels,
                ...$this->resource->counts,
            ],
            'map_arm_map' => [
                ...$this->resource->labels,
                ...IdTitleArmResource::make($this->resource)->resolve($request),
                ...$this->resource->counts,
            ],
            'two_maps_then_arm' => [
                ...$this->resource->labels,
                ...$this->resource->counts,
                ...IdTitleArmResource::make($this->resource)->resolve($request),
            ],
            'key_arm_two_maps' => [
                'n' => 1,
                ...IdTitleArmResource::make($this->resource)->resolve($request),
                ...$this->resource->labels,
                ...$this->resource->counts,
            ],
            'two_maps_then_null_arm' => [
                ...$this->resource->labels,
                ...$this->resource->counts,
                ...NullNoteArmResource::make($this->resource)->resolve($request),
            ],
            'null_arm_then_two_maps' => [
                ...NullNoteArmResource::make($this->resource)->resolve($request),
                ...$this->resource->labels,
                ...$this->resource->counts,
            ],
            'maybe_map_before_user_arm' => [
                ...($this->resource->maybeCounts ?? []),
                ...UserResource::make($this->resource->owner)->resolve($request),
            ],
            'arm_then_maybe_map' => [
                ...TitleFlagArmResource::make($this->resource)->resolve($request),
                ...($this->resource->maybeLabels ?? []),
            ],
            'maybe_map_then_arm' => [
                ...($this->resource->maybeLabels ?? []),
                ...TitleFlagArmResource::make($this->resource)->resolve($request),
            ],
            'signature_arm_then_map' => [
                ...TagSignatureArmResource::make($this->resource)->resolve($request),
                ...$this->resource->labels,
            ],
            'signature_arm_then_maybe_key' => [
                ...TagSignatureArmResource::make($this->resource)->resolve($request),
                ...$maybe,
            ],
            'wrap_arm_then_map' => [
                ...StatusWrapArmResource::make($this->resource)->resolve($request),
                ...$this->resource->labels,
            ],
            'wrap_arm_then_maybe_key' => [
                ...StatusWrapArmResource::make($this->resource)->resolve($request),
                ...$maybe,
            ],
            'same_named_arm_after_map' => [...$this->resource->users, ...$contact->toArray()],
            'three_model_spreads' => [...$contact->toArray(), ...$owner->toArray(), ...$deputy->toArray()],
        ];
    }
}
