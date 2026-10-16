<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Merchant;

/**
 * An argument-less toResourceCollection() in each nested position. Over an empty receiver Laravel's guess builds a
 * plain ResourceCollection, nested as `[]`, so a guessed collection's own type takes `never[]` and a list stays as is.
 *
 * @mixin Merchant
 */
#[TsResource(model: Merchant::class)]
class GuessedCollectionKeysResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'direct' => $this->staff->toResourceCollection(),
            'loaded' => $this->whenLoaded('staff', fn ($rows) => $rows->toResourceCollection()),
            'loaded_or_empty' => $this->whenLoaded('suppliers', fn ($rows) => $rows->toResourceCollection(), []),
            'nullsafe' => $this->owner?->orders->toResourceCollection(),
            'conditional' => $this->when($request->boolean('full'), fn () => $this->suppliers->toResourceCollection()),
            'ternary' => $request->boolean('full') ? $this->staff->toResourceCollection() : null,
            // Negatives: a given class, an anonymous guess and a collection that sends the collected list are lists.
            'explicit' => $this->staff->toResourceCollection(UserResource::class),
            'anonymous' => $this->owner->teams->toResourceCollection(),
            'collected_list' => $this->owner->posts->toResourceCollection(),
        ];
    }
}
