<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AppAndCrmWrapsGrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads its model's accessor wrapping the app's `Status` twice before the CRM's (GH-160).
 *
 * @mixin AppAndCrmWrapsGrade
 */
class SameNamedWrapAccessorReadsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['statuses' => $this->statuses];
    }
}
