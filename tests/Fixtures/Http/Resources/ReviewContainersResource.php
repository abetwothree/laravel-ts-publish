<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows to a list `@return` types as a string-keyed map, and to members a map types: a shape, a map the body reads
 * as `mixed` values, and an array it declares nothing of (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewContainersResource extends JsonResource
{
    /**
     * @return array{keyed: array<string, array{by: CrmUser, other: User|null}>,
     *     rows: list<array{m: array<string, int>, meta: list<string>, loose: array<string, string>}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'keyed' => array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
            'rows' => array_map(fn ($r) => [
                'm' => ['a' => cache('a'), 'b' => $r->n],
                'meta' => $r->meta,
                'loose' => $r->loose,
            ], $this->resource->rows),
        ];
    }
}
