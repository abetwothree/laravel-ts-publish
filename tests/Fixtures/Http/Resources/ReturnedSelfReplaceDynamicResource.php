<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable is replaced by a literal reading it after a key the walk cannot name.
 *
 * @mixin Tag
 */
class ReturnedSelfReplaceDynamicResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];
        $data[$request->string('k', 'z')->toString()] = 1;
        $data = ['x' => $data['id'] ?? 0, 'y' => $this->name];

        return $data;
    }
}
