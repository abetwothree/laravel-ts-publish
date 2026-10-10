<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ReceiverPairHandover;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource that reads ReceiverPairHandover's accessors, and makes the same calls itself, on receivers
 * holding two models that share a name.
 *
 * @mixin ReceiverPairHandover
 */
class ReceiverPairResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'replica' => $this->replica,
            'reviewer_copy' => $this->reviewer_copy,
            'reviewer_copy_wrapped' => $this->reviewer_copy_wrapped,
            'reviewer_copy_or_label' => $this->reviewer_copy_or_label,
            'copy' => $this->reviewable?->withoutRelations(),
            'copy_wrapped' => $request->boolean('crm') ? $this->reviewable?->withoutRelations() : null,
            'replica_direct' => $request->boolean('crm') ? ($this->sender ?? $this->receiver)?->replicate() : null,
        ];
    }
}
