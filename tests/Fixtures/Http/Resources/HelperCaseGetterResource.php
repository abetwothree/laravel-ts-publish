<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HelperCaseGetterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps and maps accessors whose getters return the case a vague model helper returns.
 *
 * @mixin HelperCaseGetterModel
 */
class HelperCaseGetterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'held' => $this->held_status,
            'held_resource' => EnumResource::make($this->held_status),
            'held_old_resource' => EnumResource::make($this->held_old),
            'held_values' => array_map(fn ($s) => $s->value, [$this->held_status]),
        ];
    }
}
