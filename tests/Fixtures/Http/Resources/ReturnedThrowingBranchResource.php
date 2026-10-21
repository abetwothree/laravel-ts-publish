<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable is written in a branch that then throws, so only the other one returns.
 *
 * @mixin Tag
 */
class ReturnedThrowingBranchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        if ($request->boolean('fail')) {
            $data['a'] = 1;

            throw new RuntimeException('Unavailable.');
        } else {
            $data['a'] = 'x';
        }

        return $data;
    }
}
