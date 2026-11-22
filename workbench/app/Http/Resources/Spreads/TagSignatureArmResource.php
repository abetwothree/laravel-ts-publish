<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\Spreads\ArmShelf;

/**
 * An arm whose members no reader lists: named keys beside a template-literal signature (GH-161 D3).
 *
 * @property-read ArmShelf $resource
 */
class TagSignatureArmResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = ['id' => 1, 'name' => $this->resource->title];

        foreach (['east', 'west'] as $side) {
            $data["{$side}_tag"] = 'Tag';
        }

        return $data;
    }
}
