<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A parent whose undocumented toArray() reads a helper's value, which a child returns whole. */
class GapModeParentResource extends JsonResource
{
    /**
     * A key no docblock types.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['q' => $this->parentRead()];
    }

    /**
     * The parent's value.
     */
    protected function parentRead(): string
    {
        return 'q';
    }
}
