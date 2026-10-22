<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * A member write on a local between its early-exit `instanceof` guard and the reads after it, which leaves the class
 * the guard proves. `imageable` is a morphTo over int-keyed Post and User and string-keyed Product.
 *
 * @mixin Image
 */
final class MemberWriteGuardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $m = $this->imageable;

        if (! $m instanceof Post) {
            return [];
        }

        $m->touched = true;

        return ['title' => $m->title, 'key' => $m->getKey()];
    }
}
