<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * A test-only resource reading a helper whose vague `array` return leaves its body to type the shape, with no import.
 *
 * @mixin Post
 */
class EnumCaseShapeHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'shape' => $this->caseShape(),
        ];
    }

    /**
     * A shape holding an enum case beside a number, under a return type that names neither.
     */
    protected function caseShape(): array
    {
        return ['s' => Status::Draft, 'n' => 1];
    }
}
