<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabelSerializePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sends only() of its post, which reads each attribute off the model, so a serializing cast sends get()'s value.
 *
 * @mixin LabelSerializePost
 */
final class LabelSerializeOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->only(['title', 'content', 'user_id']);
    }
}
