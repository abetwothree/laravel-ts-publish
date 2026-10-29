<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Exercises: a value-position closure, and a helper read as a value, that build an array in a variable through key
 * writes and return it. Each reads the variable through its writes, as a method's own returned variable is read, and
 * a helper's literal return stands beside it as one more arm.
 *
 * @mixin Post
 */
class PostBuiltValuesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'built' => $this->when($this->published_at !== null, function () {
                $built = ['id' => $this->id];
                $built['title'] = $this->title;

                return $built;
            }),
            'branched' => $this->when($this->published_at !== null, function () {
                $built = ['id' => $this->id];

                if ($this->is_pinned) {
                    $built['flag'] = 'pinned';
                } else {
                    $built['flag'] = 0;
                }

                return $built;
            }),
            'key_written' => $this->keyWritten($request),
            'unset_beside' => $this->unsetBeside($request),
        ];
    }

    /**
     * The post's id alone for a short request, else its id and title written key by key.
     *
     * @return array<string, mixed>
     */
    private function keyWritten(Request $request): array
    {
        if ($request->boolean('short')) {
            return ['id' => $this->id];
        }

        $data = ['id' => $this->id];
        $data['title'] = $this->title;

        return $data;
    }

    /**
     * The post's id, beside a variable that drops the key it adds.
     *
     * @return array<string, mixed>
     */
    private function unsetBeside(Request $request): array
    {
        if ($request->boolean('short')) {
            return ['id' => $this->id];
        }

        $data = ['id' => $this->id, 'gone' => 'x'];
        unset($data['gone']);

        return $data;
    }
}
