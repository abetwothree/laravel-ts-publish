<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Hands mergeWhen() and mergeUnless() a side the analysis cannot read as an array, which merges no key it knows.
 *
 * @mixin Post
 */
class MergeUnreadableDefaultResource extends JsonResource
{
    /** Pairs each readable side with a `null`, a method call, a spread or a closure Laravel cannot call. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->mergeWhen($this->id > 0, ['null_default' => $this->title], null),
            $this->mergeWhen($this->id > 0, ['call_default' => $this->title], $this->fallbackFields()),
            $this->mergeUnless($this->id > 0, $this->resource, ['default_only' => 1]),
            $this->mergeWhen($this->id > 0, ['spread_default' => $this->title], ...[['spread_default' => 1]]),
            $this->mergeWhen($this->id > 0, ['needs_arg_default' => $this->title], fn ($x) => ['needs_arg_default' => 1]),
        ];
    }

    /**
     * A helper the default position calls, whose keys the analysis does not read there.
     *
     * @return array<string, string>
     */
    public function fallbackFields(): array
    {
        return ['call_default' => 'x'];
    }
}
