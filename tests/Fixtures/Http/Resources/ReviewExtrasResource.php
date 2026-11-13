<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Maps rows under `@return` members the body never sends: a required one, an optional one and a nested required one
 * (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewExtrasResource extends JsonResource
{
    /**
     * @return array{required: list<array{by: CrmUser, other: User|null, extra: string}>,
     *     optional: list<array{by: CrmUser, extra?: Post, other: User|null}>,
     *     nested: list<array{by: CrmUser, box: array{who: User|null, extra: int}}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'required' => array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
            'optional' => array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
            'nested' => array_map(fn ($r) => ['by' => $r->author, 'box' => ['who' => cache('who')]], $this->resource->rows),
        ];
    }
}
