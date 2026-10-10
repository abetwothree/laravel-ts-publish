<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable, with a key the walk cannot name, sits beside only a `return []` guard.
 *
 * @mixin Tag
 */
class ReturnedGuardOnlyVariableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return [];
        }

        $data = ['id' => $this->id];
        $data[] = $this->name;

        return $data;
    }
}
