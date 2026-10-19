<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource over a workbench `Post`: a route binding on its relation, on itself and on `$this->resource`.
 *
 * @mixin Post
 */
final class CallPathPostResource extends JsonResource
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'author_route_binding_nullsafe' => $this->author?->resolveRouteBinding(1),
            'author_route_binding' => $this->author->resolveRouteBinding(1),
            'own_route_binding' => $this->resolveRouteBinding(1),
            'resource_route_binding' => $this->resource->resolveRouteBinding(1),
        ];
    }
}
