<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AuthoredRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows the app's user wrote beside a cache read the `@return` docblock types as a CRM user, a class of the same
 * name (GH-154).
 *
 * @property-read AuthoredRows $resource
 */
final class ReversedClashAuthorsResource extends JsonResource
{
    /** @return array{clash: list<array{by: User, other: CrmUser|null}>} */
    public function toArray(Request $request): array
    {
        return [
            'clash' => array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
        ];
    }
}
