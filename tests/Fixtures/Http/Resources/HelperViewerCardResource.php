<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\ViewerCardBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A test-only resource sending a post's id beside the cards a service builds: the signed-in user's, null for a guest,
 * and the author's inside a map() kept as a Collection.
 *
 * @mixin Post
 */
class HelperViewerCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'viewer' => (new ViewerCardBuilder)->card(auth()->user()),
            'authors' => collect([$this->author])->map(fn (?User $author) => (new ViewerCardBuilder)->card($author)),
        ];
    }
}
