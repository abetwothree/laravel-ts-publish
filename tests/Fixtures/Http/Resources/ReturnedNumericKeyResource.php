<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable writes an int key and an int constant key beside a named one.
 *
 * @mixin Tag
 */
class ReturnedNumericKeyResource extends JsonResource
{
    public const int FEATURED = 3;

    /** @return array<array-key, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];
        $data[42] = $this->name;
        $data[self::FEATURED] = true;

        return $data;
    }
}
