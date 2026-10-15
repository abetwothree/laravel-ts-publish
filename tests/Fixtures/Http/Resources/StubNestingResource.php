<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Supplier;

/**
 * Nests collections in keys: ones whose toArray() sends the collected list, and one with a body of its own.
 *
 * @mixin Supplier
 */
class StubNestingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'stub_new' => new StubCollectsCollection([]),
            'stub_make' => StubCollectsCollection::make([]),
            'resource_new' => new ResourceReturnCollection([]),
            'body_new' => new InheritedBodyParentCollection([]),
            'body_make' => InheritedBodyParentCollection::make([]),
        ];
    }
}
