<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a declared `$this->prop` that a local was bound to by reference before the test, and that the block
 * rewrites through that local.
 *
 * @mixin Image
 */
final class ImageableReferenceAliasGuardResource extends JsonResource
{
    public ?Model $subject = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->subject = $this->resource->imageable;
        $subject = &$this->subject;

        if ($this->subject instanceof Post) {
            $subject = $this->resource->reviewable;

            return ['k' => $this->subject?->getKey()];
        }

        return ['k' => null];
    }
}
