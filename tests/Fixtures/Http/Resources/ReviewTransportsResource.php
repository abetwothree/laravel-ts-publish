<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Moves rows holding untyped keys through a list spread, an identity map, a collection's map(), a callback spreading
 * its element beside a key, and a spread list inside a row (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewTransportsResource extends JsonResource
{
    /**
     * @return array{spread: list<array{by: CrmUser, extra: string}>, identity: list<array{by: CrmUser, extra: string}>,
     *     collected: list<array{by: CrmUser, extra: string}>, rebuilt: list<array{by: CrmUser, n: int, extra: string}>,
     *     nested: list<array{subs: list<array{a: int, b: string}>}>}
     */
    public function toArray(Request $request): array
    {
        $open = array_map(fn ($r) => [...$r->loose, 'by' => $r->author], $this->resource->rows);

        return [
            'spread' => [...$open],
            'identity' => array_map(fn ($x) => $x, array_map(fn ($r) => [...$r->loose, 'by' => $r->author], $this->resource->rows)),
            'collected' => collect(array_map(fn ($r) => [...$r->loose, 'by' => $r->author], $this->resource->rows))->map(fn ($x) => $x)->all(),
            'rebuilt' => array_map(fn ($x) => [...$x, 'n' => 1], array_map(fn ($r) => [...$r->loose, 'by' => $r->author], $this->resource->rows)),
            'nested' => array_map(fn ($r) => ['subs' => [...array_map(fn ($s) => [...$s->loose, 'a' => $s->n], $this->resource->rows)]], $this->resource->rows),
        ];
    }
}
