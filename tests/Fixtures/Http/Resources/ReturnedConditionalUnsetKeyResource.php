<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable unsets a key on one path only.
 *
 * @mixin Tag
 */
class ReturnedConditionalUnsetKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'name' => $this->name];

        if ($request->boolean('compact')) {
            unset($data['name']);
        }

        return $data;
    }
}
