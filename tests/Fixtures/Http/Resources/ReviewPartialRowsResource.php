<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows whose body types a CRM author and an app editor beside cache reads only the `@return` members type, listed
 * in another order or beside a `null` the body's string never sends (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewPartialRowsResource extends JsonResource
{
    /**
     * @return array{partial: list<array{other: User|null, by: CrmUser, ed: User, p: Post, n: int}>,
     *     partialWider: list<array{by: CrmUser, ed: User, t: string|null, p: Post, n: int, other: User|null}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'partial' => array_map(fn ($r) => [
                'by' => $r->author,
                'ed' => $r->editor,
                'p' => cache('p'),
                'n' => cache('n'),
                'other' => cache('other'),
            ], $this->resource->rows),
            'partialWider' => array_map(fn ($r) => [
                'by' => $r->author,
                'ed' => $r->editor,
                't' => $r->title,
                'p' => cache('p'),
                'n' => cache('n'),
                'other' => cache('other'),
            ], $this->resource->rows),
        ];
    }
}
