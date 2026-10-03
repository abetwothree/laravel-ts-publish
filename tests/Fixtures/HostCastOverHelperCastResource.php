<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose toArray() #[TsCasts] has no import, over a spread helper's one that has, for one key.
 *
 * @mixin Image
 */
class HostCastOverHelperCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['reviewable' => 'UserResource | null'])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->review(),
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['reviewable' => ['type' => 'UserResource | null', 'import' => '@js/types/user']])]
    private function review(): array
    {
        return ['reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource())];
    }
}
