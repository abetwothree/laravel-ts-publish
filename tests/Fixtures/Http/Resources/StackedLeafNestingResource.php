<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Supplier;

/**
 * Nests a body-less collection naming no API resource, whose parent collects one by its name.
 *
 * @mixin Supplier
 */
class StackedLeafNestingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'leaf_new' => new F38NamedLeafCollection([]),
            'leaf_make' => F38NamedLeafCollection::make([]),
        ];
    }
}
