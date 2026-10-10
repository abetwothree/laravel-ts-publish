<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose toArray() #[TsCasts] retypes a key that reads Image::reviewable()'s two User models.
 *
 * @mixin Image
 */
class MethodCastMorphModelResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['reviewable' => ['type' => 'User | null', 'import' => '@js/types/user']])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewable' => $this->reviewable,
        ];
    }
}
