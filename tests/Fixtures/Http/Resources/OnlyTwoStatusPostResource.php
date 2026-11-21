<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\TwoStatusPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource that keeps, through its own `only()`, accessors typed as two enums that share a name: a union
 * of them and a shape alternating them (GH-160).
 *
 * @mixin TwoStatusPost
 */
final class OnlyTwoStatusPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->only(['either_status', 'alternating_statuses']);
    }
}
