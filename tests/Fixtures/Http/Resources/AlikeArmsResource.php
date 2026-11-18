<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose two returns write each key alike but for a case value one of them writes beside the
 * status's value, so the key's arms are alike once the case value is absorbed (GH-166).
 *
 * @mixin Post
 */
class AlikeArmsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $draft = $request->boolean('draft');

        if ($this->id > 1) {
            return [
                'obj' => ['s' => $draft ? Status::Draft->value : $this->status->value],
                'lst' => [$draft ? Status::Draft->value : $this->status->value],
                'pair' => [$draft ? Status::Draft->value : $this->status->value, 1],
            ];
        }

        return [
            'obj' => ['s' => $this->status->value],
            'lst' => [$this->status->value],
            'pair' => [$this->status->value, 1],
        ];
    }
}
