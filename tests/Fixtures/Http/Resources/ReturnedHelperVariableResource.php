<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable starts from the resource's own helper.
 *
 * @mixin Tag
 */
class ReturnedHelperVariableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->basics();
        $data['label'] = strtoupper($this->name);

        return $data;
    }

    /** @return array<string, mixed> */
    protected function basics(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
