<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows whose shape spreads an array it declares nothing of, and whose member the `@return` lists while the body
 * sends one shape, beside members a vague `@return` types where the body reads nothing (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewOpenRowsResource extends JsonResource
{
    /**
     * @return array{spread: list<array{by: CrmUser, extra: string}>,
     *     listed: list<array{by: CrmUser, m: list<array{a: int}>}>,
     *     vague: list<array{by: CrmUser, tags: list<mixed>, meta: array<string, mixed>, raw: array}>,
     *     box: array{n: int, tags: list<mixed>, meta: array<string, mixed>}}
     */
    public function toArray(Request $request): array
    {
        return [
            'spread' => array_map(fn ($r) => [...$r->loose, 'by' => $r->author], $this->resource->rows),
            'listed' => array_map(fn ($r) => ['by' => $r->author, 'm' => ['a' => cache('a')]], $this->resource->rows),
            'vague' => array_map(
                fn ($r) => ['by' => $r->author, 'tags' => cache('t'), 'meta' => cache('m'), 'raw' => cache('r')],
                $this->resource->rows,
            ),
            'box' => ['n' => count($this->resource->rows), 'tags' => cache('t'), 'meta' => cache('m')],
        ];
    }
}
