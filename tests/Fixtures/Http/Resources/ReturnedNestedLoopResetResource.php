<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable re-sets a key in a loop inside a loop, either of which may not run.
 *
 * @mixin Tag
 */
class ReturnedNestedLoopResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'mode' => 1];

        for ($i = 0; $i < $request->integer('n'); $i++) {
            for ($j = 0; $j < $request->integer('m'); $j++) {
                $data['mode'] = 'deep';
                $data['seen'] = true;
            }
        }

        return $data;
    }
}
