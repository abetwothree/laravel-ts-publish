<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CrmAuthoredRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Spreads a helper whose `@return` fills an app user beside the CRM author its body types inside a nested literal,
 * which reads a docblock fill with no class (GH-154).
 *
 * @property-read CrmAuthoredRows $resource
 */
final class NestedHelperSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['payload' => [...$this->rowsPart()]];
    }

    /**
     * The rows, each with its reviewer read back from the cache.
     *
     * @return array{rows: list<array{by: CrmUser, other: User|null}>}
     */
    private function rowsPart(): array
    {
        return ['rows' => array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows)];
    }
}
