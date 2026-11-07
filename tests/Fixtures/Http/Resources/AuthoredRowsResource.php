<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AuthoredRow;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\AuthoredRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\User;

/**
 * Maps rows whose body types the author, a model, beside a cache read no rule types that the `@return` docblock types
 * as an enum or as that model again (GH-154).
 *
 * @property-read AuthoredRows $resource
 */
final class AuthoredRowsResource extends JsonResource
{
    /**
     * @return array{statuses: list<array{by: User, status: Status}>,
     *     typed: list<array{by: User, status: Status}>, reviewers: list<array{by: User, other: User|null}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'statuses' => array_map(fn ($r) => ['by' => $r->author, 'status' => cache('status')], $this->resource->rows),
            'typed' => array_map(fn (AuthoredRow $r) => ['by' => $r->author, 'status' => cache('status')], $this->resource->rows),
            'reviewers' => array_map(fn ($r) => ['by' => $r->author, 'other' => cache('reviewer')], $this->resource->rows),
        ];
    }
}
