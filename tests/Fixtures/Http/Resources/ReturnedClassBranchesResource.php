<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose two returned literals type a resource, an enum and a model key with different classes.
 *
 * @mixin Post
 */
class ReturnedClassBranchesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('p')) {
            return ['id' => $this->id, 'x' => CategoryResource::make($this->categoryRel), 's' => $this->priority, 'who' => $this->categoryRel];
        }

        return ['id' => $this->id, 'x' => UserResource::make($this->author), 's' => $this->status, 'who' => $this->author];
    }
}
