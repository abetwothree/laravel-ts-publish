<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\TwoStatusPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource reading getters that alternate two same-named enums, at the top level and a level down (GH-81).
 *
 * @mixin TwoStatusPost
 */
final class TwoStatusPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'alternating' => $this->alternating_statuses,
            'declared' => $this->declared_statuses,
            'nested' => ['x' => $this->declared_statuses],
        ];
    }
}
