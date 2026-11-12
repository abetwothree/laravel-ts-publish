<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CrmAuthoredRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Spreads a helper whose own `@return` types a key no rule reads, under a `toArray()` `@return` that types a member the
 * helper's leaves out (GH-154).
 *
 * @property-read CrmAuthoredRows $resource
 */
final class DeclaredHelperRowsResource extends JsonResource
{
    /** @return array{rows: list<array{by: CrmUser, other: User|null}>} */
    public function toArray(Request $request): array
    {
        return [...$this->rowsPart()];
    }

    /**
     * The rows, read back from the cache.
     *
     * @return array{rows: list<array{by: CrmUser, other: mixed}>}
     */
    private function rowsPart(): array
    {
        return ['rows' => cache('rows')];
    }
}
