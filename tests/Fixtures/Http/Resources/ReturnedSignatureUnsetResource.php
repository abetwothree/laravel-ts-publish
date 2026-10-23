<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable writes an interpolated key, then unsets a literal key it can name.
 *
 * @mixin Tag
 */
class ReturnedSignatureUnsetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $suffix = $request->string('suffix')->toString();
        $data = ['id' => $this->id, 'slug' => $this->slug];
        $data["slug{$suffix}"] = $this->name;
        unset($data['slug']);

        return $data;
    }
}
