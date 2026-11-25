<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Enum wraps a helper returns or is passed (GH-108): a ternary of two wraps, a wrap over `whenHas()`, and a seed the
 * helper writes a key into and returns.
 *
 * @mixin Post
 */
class WrapHelpersResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'picked' => $this->pick(),
            'status_wrap' => $this->statusWrap(),
            'card' => $this->stamp(['status' => EnumResource::make($this->status)]),
        ];
    }

    /**
     * The status's or the visibility's resource.
     */
    private function pick()
    {
        return $this->is_pinned ? EnumResource::make($this->status) : EnumResource::make($this->visibility);
    }

    /**
     * The status's resource, which Laravel drops with its key when the attribute is missing.
     */
    private function statusWrap()
    {
        return EnumResource::make($this->whenHas('status'));
    }

    /**
     * The given card, stamped.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stamp(array $data): array
    {
        $data['stamped'] = true;

        return $data;
    }
}
