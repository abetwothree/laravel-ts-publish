<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Attachment;
use Workbench\App\Models\Post;

/**
 * Narrowing fixture: `attachable` is a morphTo, so `$parent` holds a union until an early-return
 * `instanceof` guard proves it a Post. `$record`'s ternary narrows the same way in one expression.
 *
 * @mixin Attachment
 */
final class NarrowedParentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $record = $this->attachable;

        return [
            'parent' => $this->whenLoaded('attachable', function () {
                $parent = $this->attachable;

                if (! $parent || ! $parent instanceof Post) {
                    return null;
                }

                return [
                    'title' => $parent->title,
                    'class' => $parent::className(),
                    'morph' => $parent->getMorphClass(),
                ];
            }),
            'record_title' => $record instanceof Post ? $record->title : null,
        ];
    }
}
