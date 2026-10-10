<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Its returned array names `state` twice. PHP keeps the last value in the first position, so the method's cast
 * must retype the entry that publishes, not only the first.
 *
 * @mixin Post
 */
class DuplicateKeyCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[TsCasts(['state' => "'draft' | 'published'"])]
    public function toArray(Request $request): array
    {
        return [
            'state' => $this->id,
            'title' => $this->title,
            'state' => $this->title,
        ];
    }
}
