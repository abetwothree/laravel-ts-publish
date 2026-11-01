<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable writes a key from another key it holds, one no reading types.
 *
 * @mixin Tag
 */
class ReturnedSelfReadUnknownResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'q' => $request->input('q')];
        $data['v'] = $data['q'] ?? 'none';

        return $data;
    }
}
