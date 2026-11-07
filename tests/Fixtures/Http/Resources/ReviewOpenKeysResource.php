<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Builds shapes through array_merge(), a write after a spread of an untyped array, a helper returning such a spread, a
 * closed literal beside an open one of the same text, and union arms one open and one closed (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewOpenKeysResource extends JsonResource
{
    /**
     * @return array{collision: list<array{inner: array{a: int, b: string}, other: array{a: int, b: string}}>,
     *     merged: list<array{by: CrmUser, n: int, extra: string}>, helper: list<array{by: CrmUser, extra: string}>,
     *     written: list<array{by: CrmUser, n: int, extra: string}>,
     *     either: list<array{by: CrmUser, n?: int, extra: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'collision' => array_map(fn ($r) => ['inner' => [...$r->loose, 'a' => $r->n], 'other' => ['a' => $r->n]], $this->resource->rows),
            'merged' => array_map(fn ($r) => array_merge(['by' => $r->author], ['n' => $r->n]), $this->resource->rows),
            'helper' => array_map(fn (ReviewRow $r) => $this->looseRow($r), $this->resource->rows),
            'written' => array_map(function ($r) {
                $row = [...$r->loose, 'by' => $r->author];
                $row['n'] = $r->n;

                return $row;
            }, $this->resource->rows),
            'either' => array_map(
                fn ($r) => $r->n > 0 ? [...$r->loose, 'by' => $r->author] : ['by' => $r->author, 'n' => $r->n],
                $this->resource->rows,
            ),
        ];
    }

    /**
     * A row's untyped keys beside its author.
     *
     * @return array<string, mixed>
     */
    private function looseRow(ReviewRow $r): array
    {
        return [...$r->loose, 'by' => $r->author];
    }
}
