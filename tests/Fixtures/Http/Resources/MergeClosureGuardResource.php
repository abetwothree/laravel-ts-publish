<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose merge closure narrows its own local by an `instanceof` guard. `imageable` is a morphTo
 * over int-keyed Post and User and string-keyed Product.
 *
 * @mixin Image
 */
final class MergeClosureGuardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->merge(function () {
                $owner = $this->imageable;

                if ($owner instanceof Post) {
                    return ['owner_key' => $owner->getKey()];
                }

                return [];
            }),
        ];
    }
}
