<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable writes a key from another key it holds, a missing config value.
 *
 * @mixin Tag
 */
class ReturnedSelfReadConfigResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'cfg' => config('app.missing_key')];
        $data['label'] = $data['cfg'] ?? 'none';

        return $data;
    }
}
