<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows whose body types a CRM author and an app editor under `@return` members that swap those classes, add `null`
 * to a string, or list members in another order (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewRowsResource extends JsonResource
{
    /**
     * @return array{swapped: list<array{by: User, ed: CrmUser, other: User|null}>,
     *     wider: list<array{by: CrmUser, ed: User, t: string|null, other: User|null}>,
     *     reordered: list<array{other: User|null, by: CrmUser, ed: User}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'swapped' => array_map(fn ($r) => ['by' => $r->author, 'ed' => $r->editor, 'other' => cache('other')], $this->resource->rows),
            'wider' => array_map(fn ($r) => ['by' => $r->author, 'ed' => $r->editor, 't' => $r->title, 'other' => cache('other')], $this->resource->rows),
            'reordered' => array_map(fn ($r) => ['by' => $r->author, 'ed' => $r->editor, 'other' => cache('other')], $this->resource->rows),
        ];
    }
}
