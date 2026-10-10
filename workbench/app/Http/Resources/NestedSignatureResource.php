<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Index signatures inside nested shapes: one beside a named key its pattern matches, one whose text holds a backslash.
 *
 * @mixin Post
 */
final class NestedSignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'box' => [...$this->gs(), 'price_tag' => 5],
            'units' => [...$this->unitLabels()],
        ];
    }

    /** The body types these values. */
    public function gs(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = 'Tag';
        }

        return $data;
    }

    /** Keys whose literal text holds a backslash, which the signature's template text doubles. */
    public function unitLabels(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}\\unit"] = 'Unit';
        }

        return $data;
    }
}
