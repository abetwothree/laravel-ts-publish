<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Sends int keys the docblock spells bare, and a key two template keys may each write (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewKeySpellingsResource extends JsonResource
{
    /**
     * @return array{zero: list<array{0: int, by: CrmUser}>, nested: list<array{1: array{a: int}, by: CrmUser}>,
     *     classed: list<array{0: CrmUser, n: int}>, open: list<array{0: array{a: int, b: string}, by: CrmUser}>,
     *     twice: list<array{by: CrmUser, x_y: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'zero' => array_map(fn ($r) => [0 => cache('x'), 'by' => $r->author], $this->resource->rows),
            'nested' => array_map(fn ($r) => [1 => ['a' => cache('a')], 'by' => $r->author], $this->resource->rows),
            'classed' => array_map(fn ($r) => [0 => cache('u'), 'n' => $r->n], $this->resource->rows),
            'open' => array_map(fn ($r) => [0 => [...$r->loose, 'a' => $r->n], 'by' => $r->author], $this->resource->rows),
            'twice' => array_map(fn ($r) => ['by' => $r->author, "x_{$r->title}" => $r->n, "{$r->title}_y" => $r->title], $this->resource->rows),
        ];
    }
}
