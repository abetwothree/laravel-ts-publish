<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose returned variable sets a key on one path, then re-sets it on every path.
 */
class ReturnedRequiredResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $d = [];

        if ($request->boolean('x')) {
            $d['a'] = 1;
        }

        $d['a'] = 'v';

        return $d;
    }
}
