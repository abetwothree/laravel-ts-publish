<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Workbench\App\Models\Post;

/**
 * Fixture: builds its response in a local, then merges keys into it inside an `if` and a loop, which keeps every key
 * the local held. The merged keys stay unpublished, the known gap of a returned variable the walk reads only partly.
 *
 * @mixin Post
 */
class MergedPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'title' => $this->title];

        if ($request->boolean('full')) {
            $data = array_merge($data, ['body' => 'b']);
        }

        if ($request->boolean('more')) {
            $data = Arr::add($data, 'more', 1);
        }

        foreach (['a', 'b'] as $key) {
            $data = array_merge($data, [$key => true]);
        }

        return $data;
    }
}
