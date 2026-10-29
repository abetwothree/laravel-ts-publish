<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable unsets a literal key and a request-named key in one statement.
 *
 * @mixin Tag
 */
class ReturnedMixedUnsetKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $hidden = $request->string('hide')->toString();
        $data = ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
        unset($data['name'], $data[$hidden]);

        return $data;
    }
}
