<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Workbench\App\Models\Post;

/**
 * A test-only resource sending a post's id beside the signed-in user read through `auth()`, `Auth` and `request()`.
 *
 * @mixin Post
 */
class SignedInViewerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'helper' => auth()->user()?->name,
            'facade' => Auth::user()?->email,
            'request' => request()->user()?->name,
        ];
    }
}
