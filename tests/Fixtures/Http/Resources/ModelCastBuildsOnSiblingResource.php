<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SiblingCastPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;

/**
 * A test-only resource whose model's cast builds on the resource another key sends.
 *
 * @mixin SiblingCastPost
 */
class ModelCastBuildsOnSiblingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['title' => $this->title, 'other' => new UserResource($this->author)];
    }
}
