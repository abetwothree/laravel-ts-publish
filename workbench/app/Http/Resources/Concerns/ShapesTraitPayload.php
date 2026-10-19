<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * Supplies a resource's whole toArray() from its own file.
 */
trait ShapesTraitPayload
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'shaped_by' => 'trait',
        ];
    }
}
