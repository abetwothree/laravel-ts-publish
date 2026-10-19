<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\SupplierCollection;
use Workbench\App\Models\Merchant;

/**
 * Nests a workbench collection whose own `toArray()` sends a body, by `new` and by `make()`.
 *
 * @mixin Merchant
 */
class NestedSupplierListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'suppliers' => new SupplierCollection([]),
            'suppliers_made' => SupplierCollection::make([]),
        ];
    }
}
