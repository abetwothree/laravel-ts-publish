<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DroppedArms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\DroppedArms\ArmHolder;

/**
 * A held local that keeps the `cache()` list a spread may write over an earlier string (GH-162 E1 guard).
 *
 * @property-read ArmHolder $resource
 */
class FoldSiteDropResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'spread_local_held' => [...$this->spreadLocalHeld()],
        ];
    }

    /**
     * A local that may write `k` from the cache, spread over an earlier `k` into a local that is returned.
     *
     * @return array{k: string|list<int>}
     */
    private function spreadLocalHeld(): array
    {
        $maybe = $this->resource->flag ? ['k' => cache('k')] : [];
        $data = ['k' => 'x', ...$maybe];

        return $data;
    }
}
