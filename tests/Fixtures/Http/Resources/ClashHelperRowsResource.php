<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CrmAuthoredRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Spreads a helper whose own `@return` fills an app user beside the CRM author its body types, under a `toArray()`
 * `@return` that types a member the helper's leaves out (GH-154).
 *
 * @property-read CrmAuthoredRows $resource
 */
final class ClashHelperRowsResource extends JsonResource
{
    /** @return array{rows: list<array{by: CrmUser, ed: User, other: User|null}>} */
    public function toArray(Request $request): array
    {
        return [...$this->rowsPart()];
    }

    /**
     * The rows, each with its editor and reviewer read back from the cache.
     *
     * @return array{rows: list<array{by: CrmUser, ed: User}>}
     */
    private function rowsPart(): array
    {
        return ['rows' => array_map(fn ($r) => ['by' => $r->author, 'ed' => cache('ed'), 'other' => cache('other')], $this->resource->rows)];
    }
}
