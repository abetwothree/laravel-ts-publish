<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Bindings that hold inside one block: a local assigned in an `if` or a `try` block, read after it there; an early-exit
 * `instanceof` guard nested in a block, for the rest of that block; and a positive `instanceof` test, in its block.
 *
 * `imageable` is a morphTo over int-keyed Post and User and string-keyed Product, so a narrowed key read loses the
 * string arm.
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
        $owner = $this->imageable;
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

        foreach ([1] as $_) {
            if (! $owner instanceof Post) {
                continue;
            }

            $data['loop_post_key'] = $owner->getKey();
        }

        if ($request->has('post')) {
            if (! $owner instanceof Post) {
                return $data;
            }

            $data['guarded_post_key'] = $owner->getKey();
        }

        if ($owner instanceof Post) {
            $data['positive_title'] = $owner->title;
        } elseif ($owner instanceof User) {
            $data['positive_email'] = $owner->email;
        }

        $data['loaded_post_key'] = $this->whenLoaded('imageable', function ($loaded) {
            if (! $loaded instanceof Post) {
                return null;
            }

            return ['k' => $loaded->getKey()];
        });

        return $data;
    }
}
