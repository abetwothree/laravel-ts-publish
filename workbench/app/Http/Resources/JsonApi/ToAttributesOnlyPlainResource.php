<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A plain API resource declaring toAttributes() and no toArray(), which Laravel 12.45 and later sends.
 *
 * @mixin Post
 */
class ToAttributesOnlyPlainResource extends JsonResource
{
    /**
     * The keys the resource sends.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return ['headline' => $this->title];
    }
}
