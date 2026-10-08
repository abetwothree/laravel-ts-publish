<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource with no model, whose returned variable starts from `parent::toArray()`, which reads as nothing.
 */
class ReturnedUnmodeledParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('a')) {
            return ['id' => 1];
        }

        $data = parent::toArray($request);
        $data['x'] = 1;

        return $data;
    }
}
