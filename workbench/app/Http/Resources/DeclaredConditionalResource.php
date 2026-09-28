<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Locals holding a conditional value the engine cannot read, or read inside a whenLoaded() closure. A key the `@var`
 * types stays optional, since Laravel drops it when the condition fails, and inside the closure a known reading of
 * the assigned value stands over the loaded relation's model.
 *
 * @mixin Post
 */
final class DeclaredConditionalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $reviewer */
        $reviewer = $this->whenLoaded('reviewer');

        /** @var array{a: int} $flags */
        $flags = $this->when($request->boolean('flags'), fn () => json_decode('{"a":1}', true));

        /** @var int $views */
        $views = $this->whenHas('view_total');

        /** @var Model $author */
        $author = $this->resource->author;

        return [
            'reviewer' => $reviewer,
            'flags' => $flags,
            'views' => $views,
            'author_name' => $this->whenLoaded('comments', fn () => $author->name),
        ];
    }
}
