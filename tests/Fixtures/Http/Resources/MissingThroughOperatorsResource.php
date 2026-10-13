<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A MissingValue carried through `??`, a `when*()` default, a ternary and a match.
 *
 * @mixin Post
 */
final class MissingThroughOperatorsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $flag = $request->boolean('flag');
        $loaded = $this->whenLoaded('author');
        /** @var User $declared */
        $declared = $this->whenLoaded('author');
        $when = $this->when($flag, 'w');
        $has = $this->whenHas('undeclared_attr');

        return [
            'local_loaded_coalesce' => $loaded ?? 'fallback',
            'declared_coalesce' => $declared ?? 'fallback',
            'local_when_coalesce' => $when ?? 'fallback',
            'local_has_coalesce' => $has ?? 'fallback',
            'direct_coalesce' => $this->whenLoaded('author') ?? 'y',
            'coalesce_right_missing' => $this->title ?? $this->whenLoaded('author'),
            'null_left_coalesce' => null ?? $this->whenLoaded('author'),
            'when_wrap' => $this->when($flag, $this->whenLoaded('author')),
            'when_wrap_default' => $this->when($flag, $this->whenLoaded('author'), 'dflt'),
            'when_true_default' => $this->when(true, $this->whenLoaded('author'), 'dflt'),
            'unless_wrap_default' => $this->unless($flag, $this->whenLoaded('author'), 'dflt'),
            'when_missing_default' => $this->when($flag, 'v', $this->whenLoaded('author')),
            'when_closure_default' => $this->when($flag, fn () => $this->whenLoaded('author'), 'dflt'),
            'when_local_default' => $this->when($flag, $loaded, 'dflt'),
            'when_not_null_default' => $this->whenNotNull($loaded, 'dflt'),
            'nested' => ['inner' => $loaded],
            'nested_coalesce' => ['inner' => $loaded ?? 'f'],
            'ternary' => $flag ? $loaded : 'f',
            'match' => match ($flag) {
                true => $loaded,
                false => 'f',
            },
            'kept' => 1,
        ];
    }
}
