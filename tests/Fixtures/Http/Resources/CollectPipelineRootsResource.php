<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Grade;
use Workbench\App\Models\Post;

/**
 * collect() pipelines over a literal holding a `when*()` value or int keys with gaps, kept and handed back as an array,
 * beside array_map() zipping a list of enums with a list of counts.
 *
 * @mixin Post
 */
final class CollectPipelineRootsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'when_all' => collect([$this->when(true, 1)])->all(),
            'when_values' => collect([$this->when(false, 1), 2])->values()->all(),
            'when_map_identity' => collect([$this->when(false, 1), 2])->map(fn ($v) => $v)->all(),
            'when_map_const' => collect([$this->when(false, 1), 2])->map(fn ($v) => 'x')->all(),
            'when_filter' => collect([$this->when(false, 1), 2])->filter()->all(),
            'when_loaded_map' => collect([$this->whenLoaded('author')])->map(fn ($u) => 1)->all(),
            'gapped_kept' => collect([1 => 'a', 3 => 'b'])->map(fn (string $v): string => strtoupper($v)),
            'gapped_all' => collect([1 => 'a', 3 => 'b'])->map(fn (string $v): string => strtoupper($v))->all(),
            'zipped_grades' => array_map(null, $this->grades(), $this->counts()),
        ];
    }

    /** @return list<Grade> */
    public function grades(): array
    {
        return [Grade::Pass, Grade::Fail];
    }

    /** @return list<int> */
    public function counts(): array
    {
        return [1, 2, 3];
    }
}
