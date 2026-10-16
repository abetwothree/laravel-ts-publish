<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A plain API resource declaring both toAttributes() and toArray(): Laravel 12.45 and later sends toAttributes().
 *
 * @mixin Post
 */
class ToAttributesBesideToArrayResource extends JsonResource
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

    /**
     * The keys a caller of toArray() reads, which the response never sends.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['title' => $this->title, 'content' => $this->content];
    }
}
