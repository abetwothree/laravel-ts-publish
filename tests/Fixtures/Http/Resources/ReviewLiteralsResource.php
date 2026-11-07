<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows whose scalars the `@return` docblock narrows to literals: beside the `null` a nullable string reads, and
 * beside a `null` the body's string and int never send (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewLiteralsResource extends JsonResource
{
    /**
     * @return array{union: list<array{k: 'a'|'b', j: 'a'|'b'|null, by: CrmUser}>,
     *     besideNull: list<array{k: 'a'|null, n: 1|null, by: CrmUser}>, top: 'rows'|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'union' => array_map(fn ($r) => ['k' => $r->note, 'j' => $r->note, 'by' => $r->author], $this->resource->rows),
            'besideNull' => array_map(fn ($r) => ['k' => $r->title, 'n' => $r->n, 'by' => $r->author], $this->resource->rows),
            'top' => $this->resource->kind,
        ];
    }
}
