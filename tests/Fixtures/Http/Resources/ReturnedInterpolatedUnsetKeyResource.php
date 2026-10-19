<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable unsets a key built around a request value, which can name any key.
 *
 * @mixin Tag
 */
class ReturnedInterpolatedUnsetKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'name' => $this->name];
        }

        $suffix = $request->string('hide')->toString();
        $data = ['id' => $this->id, 'slug' => $this->slug];
        unset($data["slug{$suffix}"]);

        return $data;
    }
}
