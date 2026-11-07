<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Sends shapes under `@return` members that list shapes, or offer a map or a list, a template key beside a member its
 * pattern covers, and a value only a ternary's `null` arm types (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewListedShapesResource extends JsonResource
{
    /**
     * @return array{nullable: list<array{m: list<array{a: int}|null>}>, either: list<array{m: list<array{a: int}|array{b: string}>}>,
     *     recordOrList: list<array{m: array<string, int>|list<int>}>, top: array<string, int>|list<int>,
     *     vagueNull: list<array{tags: list<mixed>}>, covered: list<array{by: CrmUser, x_tag: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'nullable' => array_map(fn ($r) => ['m' => ['a' => cache('a')]], $this->resource->rows),
            'either' => array_map(fn ($r) => ['m' => ['a' => cache('a')]], $this->resource->rows),
            'recordOrList' => array_map(fn ($r) => ['m' => ['a' => cache('a')]], $this->resource->rows),
            'top' => ['a' => cache('a'), 'b' => $this->resource->n],
            'vagueNull' => array_map(fn ($r) => ['tags' => $r->n > 0 ? cache('t') : null], $this->resource->rows),
            'covered' => array_map(fn ($r) => ['by' => $r->author, "{$r->title}_tag" => $r->n], $this->resource->rows),
        ];
    }
}
