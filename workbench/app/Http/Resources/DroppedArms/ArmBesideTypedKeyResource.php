<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DroppedArms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\DroppedArms\ArmHolder;

/**
 * A key the `@return` does not name, holding an arm no rule types beside two same-named users (GH-161).
 *
 * @property-read ArmHolder $resource
 */
class ArmBesideTypedKeyResource extends JsonResource
{
    /**
     * @return array{n: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'n' => 1,
            'users' => $this->resource->flag
                ? [$this->resource->owner]
                : [$this->resource->pick === 'a' ? $this->resource->contact : cache('u')],
        ];
    }
}
