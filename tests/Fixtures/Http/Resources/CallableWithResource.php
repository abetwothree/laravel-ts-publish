<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Workbench\App\Models\Post;

/**
 * A post's id, with keys its response's `with()` holds as callables or an Inertia prop object, which `json_encode()`
 * writes as `{}`, directly, in a list, from a `map()` callback, through a helper and on some requests only.
 *
 * @mixin Post
 */
class CallableWithResource extends JsonResource
{
    /**
     * The post's id.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }

    /**
     * The keys beside the body, each callable or prop object among them sent as `[]`.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $with = [
            'cb' => fn () => 1,
            'prop' => Inertia::always(2),
            'either' => $request->boolean('shown') ? fn () => 3 : 'x',
            'helper' => $this->helper(),
            'listed' => [fn () => 7, 8],
            'mapped' => collect([1])->map(fn (int $i) => fn () => $i),
        ];
        $with['written'] = fn () => 4;

        return $with;
    }

    /**
     * Keys a helper returns, one of them a callable.
     *
     * @return array<string, mixed>
     */
    private function helper(): array
    {
        return ['cb' => fn () => 5, 'n' => 6];
    }
}
