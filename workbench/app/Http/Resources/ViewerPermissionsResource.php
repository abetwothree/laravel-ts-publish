<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fixture for a resource whose model nothing can resolve — no TsResource attribute, no mixin or
 * extends tag, no typed $resource, no naming-convention match. Constructed over an Authorizable, so
 * `can()` forwards through JsonResource::__call and must type as boolean with the model arm gone.
 */
class ViewerPermissionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'can_publish' => $this->can('publish'),
        ];
    }
}
