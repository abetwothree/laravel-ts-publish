<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable spreads a bare `with()` beside a literal. The analyzer reads it as the
 * subject's with() and declines it, since the constructor writes `$with`; PHP runs Laravel's `with()` helper, which
 * returns its argument.
 *
 * @mixin Tag
 */
class ReturnedDeclinedBareSpreadResource extends JsonResource
{
    /**
     * Wrap a tag, keeping its name in `$with`.
     */
    public function __construct(mixed $resource)
    {
        parent::__construct($resource);

        $this->with = ['name' => $this->resource->name];
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'name' => $this->name];
        }

        $data = ['id' => $this->id, ...with($this->with)];

        return $data;
    }
}
