<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose first return is a literal inside `try`, beside a variable it cannot read.
 *
 * @mixin Tag
 */
class ReturnedTryLiteralThenOpaqueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        try {
            return ['id' => $this->id, 'name' => $this->name];
        } catch (Throwable $e) {
            report($e);
        }

        $data = $this->resource->toArray();
        $data['links'] = 1;

        return $data;
    }
}
