<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable re-sets a key in a while loop, which may run zero times.
 *
 * @mixin Tag
 */
class ReturnedWhileLoopResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'mode' => 1];
        $left = $request->integer('n');

        while ($left-- > 0) {
            $data['mode'] = 'many';
            $data['seen'] = true;
        }

        return $data;
    }
}
