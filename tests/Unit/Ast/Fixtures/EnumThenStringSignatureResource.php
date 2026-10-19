<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Two entries of one `_state` pattern: the first reads the post's enum status, the later one a string.
 *
 * @mixin Post
 */
final class EnumThenStringSignatureResource extends JsonResource
{
    /** The enum entry, then the string one. */
    public function toArray(Request $request): array
    {
        return ["{$this->id}_state" => $this->status, "{$this->slug}_state" => 'draft'];
    }
}
