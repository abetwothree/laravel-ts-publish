<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * toArray()'s own `@return` names `User` without importing it, so PHP resolves the name to no class.
 *
 * @mixin Post
 */
final class DocShapePostResource extends JsonResource
{
    /**
     * The keys, each over a value the body cannot type.
     *
     * @return array{id: int, author: User, mode: 'draft'|'live'}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->opaque(),
            'mode' => $this->opaque(),
        ];
    }

    /** Deliberately untyped. */
    private function opaque()
    {
        return json_decode('{}');
    }
}
