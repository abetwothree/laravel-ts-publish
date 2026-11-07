<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CrmAuthoredRow;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CrmAuthoredRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows a CRM user wrote beside a cache read the `@return` docblock types as the app's own user, a class of the
 * same name: through an untyped and a typed callback, and with neither member typed by the body (GH-154).
 *
 * @property-read CrmAuthoredRows $resource
 */
final class ClashingAuthorsResource extends JsonResource
{
    /**
     * @return array{clash: list<array{by: CrmUser, other: User|null}>,
     *     typed: list<array{by: CrmUser, other: User|null}>, unread: list<array{by: CrmUser, other: User|null}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'clash' => array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
            'typed' => array_map(fn (CrmAuthoredRow $r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
            'unread' => array_map(fn ($r) => ['by' => cache('by'), 'other' => cache('other')], $this->resource->rows),
        ];
    }
}
