<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable spreads a `self::with()` the analyzer declines, beside a literal: the
 * constructor writes `$with`, so no default says what it holds.
 *
 * @mixin Tag
 */
class ReturnedDeclinedStaticSpreadResource extends JsonResource
{
    /**
     * Wrap a tag, sending its name again through with().
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

        $data = ['id' => $this->id, ...self::with($request)];

        return $data;
    }
}
