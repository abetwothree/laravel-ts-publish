<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose toArray() #[TsCasts] spells its enum resource's type name and imports it.
 *
 * @mixin Post
 */
class MethodCastWrapTypeImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['k' => ['type' => 'StatusType', 'import' => '@js/types/status']])]
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
        ];
    }
}
