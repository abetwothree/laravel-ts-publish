<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\GathersPermissions;
use Workbench\App\Models\Post;

/**
 * Every return branch of a spread method counts, and the method's own @return shape types
 * what its body cannot.
 *
 * @mixin Post
 */
final class PermissionsSpreadResource extends JsonResource
{
    use GathersPermissions;

    public function toArray(Request $request): array
    {
        return [...$this->gatherPermissions(), ...$this->gatherLabels(), ...$this->gatherChannelLabels(), ...$this->gatherRegionLabels(), 'id' => $this->id];
    }
}
