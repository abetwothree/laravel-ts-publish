<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable is replaced by a literal reading it after an appended key.
 *
 * @mixin Tag
 */
class ReturnedSelfReplaceAppendResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];
        $data[] = 'x';
        $data = ['id' => $data['id'], 'name' => $this->name];

        return $data;
    }
}
