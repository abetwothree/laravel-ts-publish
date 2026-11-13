<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource sending a post's id beside the signed-in user's badge, built by a self::make() factory inside a
 * map() kept as a Collection and outside it.
 *
 * @mixin Post
 */
class ViewerBadgeListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'badges' => collect([1])->map(fn () => ViewerBadgeResource::forViewer(auth()->user())),
            'badge' => ViewerBadgeResource::forViewer(auth()->user()),
        ];
    }
}
