<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Builds shapes whose every key the engine names, through a helper spread, a public constant, a closed literal beside
 * an open one, a template key and array_merge(), and shapes it cannot, through a spread of an untyped array, under
 * `@return` members the bodies never send (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewNamedKeysResource extends JsonResource
{
    public const string KEY = 'count';

    /**
     * @return array{spreadHelper: list<array{by: CrmUser, id: int, extra: string}>,
     *     constKey: list<array{by: CrmUser, count: int, extra: string}>,
     *     collision: list<array{inner: array{a: int, b: string}, other: array{a: int, b: string}}>,
     *     tagged: list<array{by: CrmUser, extra: string}>, merged: list<array{by: CrmUser, n: int, extra: string}>,
     *     written: list<array{by: CrmUser, n: int, extra: string}>, helper: list<array{by: CrmUser, extra: string}>,
     *     box: array{by: CrmUser, id: int, extra: string}, keyed: array{by: CrmUser, count: int, extra: string},
     *     either: list<array{by: CrmUser, n?: int, extra: string}>}
     */
    public function toArray(Request $request): array
    {
        $rows = $this->resource->rows;

        return [
            'spreadHelper' => array_map(fn (ReviewRow $r) => [...$this->rowCommon($r), 'by' => $r->author], $this->resource->rows),
            'constKey' => array_map(fn ($r) => [self::KEY => $r->n, 'by' => $r->author], $this->resource->rows),
            'collision' => array_map(fn ($r) => ['inner' => [...$r->loose, 'a' => $r->n], 'other' => ['a' => $r->n]], $this->resource->rows),
            'tagged' => array_map(fn ($r) => ['by' => $r->author, "{$r->title}_tag" => $r->n], $this->resource->rows),
            'merged' => array_map(fn ($r) => array_merge(['by' => $r->author], ['n' => $r->n]), $this->resource->rows),
            'written' => array_map(function ($r) {
                $row = [...$r->loose, 'by' => $r->author];
                $row['n'] = $r->n;

                return $row;
            }, $this->resource->rows),
            'helper' => array_map(fn (ReviewRow $r) => $this->looseRow($r), $this->resource->rows),
            'box' => [...$this->boxCommon(), 'by' => $this->resource->author],
            'keyed' => [self::KEY => count($rows), 'by' => $this->resource->author],
            'either' => array_map(
                fn ($r) => $r->n > 0 ? [...$r->loose, 'by' => $r->author] : ['by' => $r->author, 'n' => $r->n],
                $this->resource->rows,
            ),
        ];
    }

    /**
     * A row's id, the keys every row shares.
     *
     * @return array{id: int}
     */
    private function rowCommon(ReviewRow $r): array
    {
        return ['id' => $r->n];
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

    /**
     * The box's id.
     *
     * @return array{id: int}
     */
    private function boxCommon(): array
    {
        return ['id' => $this->resource->n];
    }
}
