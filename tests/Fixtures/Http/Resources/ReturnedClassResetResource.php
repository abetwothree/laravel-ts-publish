<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose returned variable gives a resource, an enum and a model key other classes in a branch.
 *
 * @mixin Post
 */
class ReturnedClassResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'x' => UserResource::make($this->author), 's' => $this->status, 'who' => $this->author];

        if ($request->boolean('p')) {
            $data['x'] = CategoryResource::make($this->categoryRel);
            $data['s'] = $this->priority;
            $data['who'] = $this->categoryRel;
        }

        return $data;
    }
}
