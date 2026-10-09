<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource over a model: `$this->resource::m()`, a forwarded `$this->m()`, `$this->resource->m()` and a relation's.
 *
 * @mixin CallPathModel
 */
final class CallPathModelResource extends JsonResource
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'resource_static_label' => $this->resource::staticLabel(),
            'resource_static_plain_date' => $this->resource::staticDate(),
            'resource_static_base_model' => $this->resource::staticBaseModel(),
            'forwarded_label' => $this->label(),
            'forwarded_plain_date' => $this->date(),
            'forwarded_base_model' => $this->baseModel(),
            'forwarded_auth_user' => $this->authUser(),
            'chained_plain_date' => $this->resource->date(),
            'chained_base_model' => $this->resource->baseModel(),
            'relation_nullsafe_base_model' => $this->sibling?->baseModel(),
            'relation_static_base_model' => $this->whenLoaded('sibling', fn () => $this->sibling::staticBaseModel()),
            'own_shadowed_label' => $this->shadowedLabel(),
        ];
    }

    /** Declares `mixed` and returns the model's string namesake, whose declaration types the call instead. */
    public function shadowedLabel(): mixed
    {
        return $this->resource->shadowedLabel();
    }
}
