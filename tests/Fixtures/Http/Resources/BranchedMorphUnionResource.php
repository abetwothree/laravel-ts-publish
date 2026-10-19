<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose two return branches each spell Image::reviewable()'s union, to pin the branch merge.
 *
 * @mixin Image
 */
class BranchedMorphUnionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->id) {
            return [
                'review' => ['subject' => $this->reviewable->toResource()],
            ];
        }

        return [
            'review' => ['subject' => $this->reviewable->toResource(), 'label' => $this->alt_text],
        ];
    }
}
