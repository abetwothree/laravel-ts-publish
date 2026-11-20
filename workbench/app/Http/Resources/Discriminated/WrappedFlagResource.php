<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\WrappedFlag;
use Workbench\App\ValueObjects\WrappedMode;

/**
 * Typed properties PHP holds as objects whose override types them as scalars: a guard tests the object, not what
 * json_encode() writes, so neither is a discriminant and every key publishes flat (GH-157).
 *
 * @mixin Post
 */
class WrappedFlagResource extends JsonResource
{
    public WrappedFlag $flag;

    public WrappedMode $mode;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'flag' => $this->flag,
            'flag_title' => $this->flag ? $this->title : null,
            'mode' => $this->mode,
            'mode_title' => match ($this->mode) {
                'on' => $this->title,
                default => null,
            },
        ];
    }
}
