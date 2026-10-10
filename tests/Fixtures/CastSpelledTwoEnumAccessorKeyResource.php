<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose #[TsCasts], with no import, adds a key its body does not return, named after a two-enum
 * accessor, and spells the type name both enums share.
 *
 * @mixin Warehouse
 */
#[TsCasts(['review_priority' => 'StatusType | null'])]
class CastSpelledTwoEnumAccessorKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
        ];
    }
}
