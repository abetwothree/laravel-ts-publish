<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns\HoldsStatusAndPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Reads its trait's properties, whose `@var` names classes this file does not import. */
final class TraitPropertyResource extends JsonResource
{
    use HoldsStatusAndPost;

    /**
     * The trait's properties.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'post' => $this->post,
        ];
    }
}
