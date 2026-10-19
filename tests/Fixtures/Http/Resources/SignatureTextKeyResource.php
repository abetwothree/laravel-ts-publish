<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose literal keys read as index signatures, in an array literal and in a key write,
 * beside the `\_x` signature its loop publishes.
 *
 * @mixin Post
 */
class SignatureTextKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, '[key: string]' => 1, '[key: `${string}\_x`]' => 5];
        $data['[key: number]'] = 2;

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_x"] = 'x';
        }

        return $data;
    }
}
