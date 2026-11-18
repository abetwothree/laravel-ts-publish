<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Literals whose keys follow a sibling key's value, read back as keys (GH-157): a helper that writes into one, a
 * collection, a spread and a key read. Each reads the literal as one object, as it did before the literal split.
 *
 * @mixin Post
 */
class ReadBackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $grouped = ['pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null];

        return [
            'stamped' => $this->stamped(['pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null]),
            'stamped_enum' => $this->stamped([
                'status' => $this->status,
                'v' => $this->status === Status::Published ? $this->published_at : null,
            ]),
            'stamped_local' => $this->stamped($grouped),
            'collected' => collect(['pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null]),
            'spread' => [...$grouped, 'x' => 1],
            'read_pinned' => $grouped['pinned'],
            'read_v' => $grouped['v'],
        ];
    }

    /**
     * The keys given, with one more written into them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stamped(array $data): array
    {
        $data['at'] = 1;

        return $data;
    }
}
