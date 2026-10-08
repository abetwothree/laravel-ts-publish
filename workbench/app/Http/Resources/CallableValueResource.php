<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Workbench\App\Models\Depot;

/**
 * First-class callables as values. A key that holds one sends the `{}` json_encode() writes for a Closure, while a
 * when() or whenLoaded() value that holds one is called first, so the key sends that call's return.
 *
 * @mixin Depot
 */
class CallableValueResource extends JsonResource
{
    /** The depot's display label. */
    public function label(): string
    {
        return 'depot';
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'length' => strlen(...),
            'upper' => Str::upper(...),
            'key' => $this->resource->getKey(...),
            'label' => $this->label(...),
            'supervisor_resource' => UserResource::make(...),
            'nested' => ['length' => strlen(...)],
            'when_key' => $this->when($request->has('key'), $this->resource->getKey(...)),
            'when_label' => $this->when($request->has('label'), $this->label(...)),
            'label_or_zero' => $this->when($request->has('zero'), 0, $this->label(...)),
            'supervisor' => $this->whenLoaded('supervisor', UserResource::make(...)),
        ];
    }
}
