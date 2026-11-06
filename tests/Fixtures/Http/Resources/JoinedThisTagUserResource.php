<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ThisTaggedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The names a user's own methods read past a tag over `$this` on a branch's last statement.
 */
final class JoinedThisTagUserResource extends JsonResource
{
    /** @var ThisTaggedUser */
    public $resource;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'static_name' => $this->resource->staticName($this->resource),
            'totals_name' => $this->resource->totalsName($this->resource),
            'union_name' => $this->resource->unionName(),
        ];
    }
}
