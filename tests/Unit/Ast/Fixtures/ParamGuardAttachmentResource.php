<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Attachment;
use Workbench\App\Models\Post;

/**
 * An early-exit guard on the morphTo parameter a whenLoaded() closure never writes, beside the unguarded form.
 *
 * @mixin Attachment
 */
final class ParamGuardAttachmentResource extends JsonResource
{
    /**
     * The guarded parameter, and the same read without the guard.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'guarded' => $this->whenLoaded('attachable', function ($parent) {
                if (! $parent instanceof Post) {
                    return null;
                }

                return ['title' => $parent->title];
            }),
            'unguarded' => $this->whenLoaded('attachable', fn ($parent) => ['title' => $parent->title]),
        ];
    }
}
