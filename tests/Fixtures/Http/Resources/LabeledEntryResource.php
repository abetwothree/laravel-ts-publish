<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource the naming convention backs with LabeledEntry, whose generic names the abstract model it extends.
 *
 * @extends JsonResource<AbstractEntry&HasLabel>
 */
final class LabeledEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'label' => $this->label(),
        ];
    }
}
