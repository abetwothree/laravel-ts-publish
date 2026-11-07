<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows to one of two shapes, or to one, under a `@return` declaring both as a union, and to a list `array_map()`
 * never returns null under a nullable `@return` (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewShapesResource extends JsonResource
{
    /**
     * @return array{either: list<array{type: 'a', by: CrmUser, x: int}|array{type: 'b', y: string}>,
     *     one: list<array{type: 'a', by: CrmUser, x: int}|array{type: 'b', y: string}>,
     *     maybe: list<array{by: CrmUser, other: User|null}>|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'either' => array_map(
                fn ($r) => $r->n > 0 ? ['type' => 'a', 'by' => $r->author, 'x' => cache('x')] : ['type' => 'b', 'y' => cache('y')],
                $this->resource->rows,
            ),
            'one' => array_map(fn ($r) => ['type' => 'a', 'by' => $r->author, 'x' => cache('x')], $this->resource->rows),
            'maybe' => array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
        ];
    }
}
