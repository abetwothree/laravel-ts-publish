<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable starts from a local and adds another with `+=`.
 *
 * @mixin Tag
 */
class ReturnedLocalOperandsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $defaults = ['note' => 'none', 'id' => 'x'];
        $extra = ['flag' => true];
        $data = $defaults;
        $data['id'] = $this->id;
        $data += $extra;

        return $data;
    }
}
