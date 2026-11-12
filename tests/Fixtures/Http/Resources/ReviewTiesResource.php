<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows under `@return` unions of shapes whose keys are alike, told apart only by a discriminant literal the body
 * widens to `string`, or by keys the body never sends (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewTiesResource extends JsonResource
{
    /**
     * @return array{payload: list<array{type: 'text', value: string}|array{type: 'count', value: int}>,
     *     kinds: list<array{type: 'text', value: string}|array{type: 'count', value: int}>,
     *     shared: list<array{v: int, a: string}|array{v: string, b: string}>,
     *     classes: list<array{type: 'user', id: int, by: CrmUser}|array{type: 'post', id: int, by: Post}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'payload' => array_map(
                fn ($r) => $r->n > 0 ? ['type' => 'text', 'value' => cache('v')] : ['type' => 'count', 'value' => cache('c')],
                $this->resource->rows,
            ),
            'kinds' => array_map(fn ($r) => ['type' => $r->title, 'value' => cache('v')], $this->resource->rows),
            'shared' => array_map(fn ($r) => ['v' => cache('v')], $this->resource->rows),
            'classes' => array_map(
                fn ($r) => $r->n > 0 ? ['type' => 'user', 'id' => $r->n, 'by' => cache('u')] : ['type' => 'post', 'id' => $r->n, 'by' => cache('p')],
                $this->resource->rows,
            ),
        ];
    }
}
