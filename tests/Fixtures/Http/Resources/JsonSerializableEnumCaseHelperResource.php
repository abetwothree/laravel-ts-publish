<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\JsonSerializableTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource reading a vague helper that holds a `JsonSerializable` enum's case, with no import.
 *
 * @mixin Post
 */
class JsonSerializableEnumCaseHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'shape' => $this->tierShape(),
        ];
    }

    /**
     * A shape holding a case whose jsonSerialize() is what json_encode() writes, under a return type naming neither.
     */
    protected function tierShape(): array
    {
        return ['t' => JsonSerializableTier::Gold];
    }
}
