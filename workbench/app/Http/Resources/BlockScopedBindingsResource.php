<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Image;

/**
 * Bindings that hold inside one block: a local assigned in an `if` or a `try` block, read after it there.
 *
 * @mixin Image
 */
final class BlockScopedBindingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        if ($request->has('size')) {
            $label = $this->url;
            $data['label'] = $label;
        }

        try {
            $bytes = $this->size_bytes;
            $data['bytes'] = $bytes;
        } catch (RuntimeException) {
        }

        return $data;
    }
}
