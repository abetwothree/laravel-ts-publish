<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Narrowing fixture: `imageable` is a morphTo over int-keyed Post, User and CRM User and string-keyed Product.
 * A ternary whose `instanceof` test (or `||` chain of them) reads the same expression as its true arm narrows
 * that arm to the tested classes, so a key read through the bound variable loses the string arm.
 *
 * @mixin Image
 */
final class NarrowedImageableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $either = $this->imageable instanceof Post || $this->imageable instanceof User ? $this->imageable : null;
        $single = $this->imageable instanceof Post ? $this->imageable : null;

        return [
            'either_id' => $either?->getKey(),
            'single_id' => $single?->getKey(),
            'open_id' => $this->imageable?->getKey(),
            'either_title' => $either instanceof Post ? $either->title : null,
        ];
    }
}
