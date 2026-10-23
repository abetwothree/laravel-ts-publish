<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A local a branch writes `null` over, read past that branch through `->`, which throws on the `null` path.
 *
 * @mixin Post
 */
final class JoinedNullMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $editor = $this->author;

        if ($this->is_pinned) {
            $editor = null;
        }

        return ['editor_name' => $editor->name];
    }
}
