<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Literal-key reads of an array whose shape a local, a passed argument, a map's element or a helper's `@return` holds,
 * beside a read of a key the shape lacks and of a `when()` slot a local holds, read bare and through `??`.
 *
 * @mixin Post
 */
final class OffsetReadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = ['a' => 1, 'b' => 'x'];
        $slots = ['a' => $this->when($request->has('a'), 1)];

        return [
            'param' => $this->readA(['a' => 1, 'b' => 'x']),
            'local' => $row['b'],
            'mapped' => collect([['a' => 1], ['a' => 2]])->map(fn ($r) => $r['a'])->all(),
            'missing' => $row['c'] ?? null,
            'tuple' => $this->pair()[1],
            'slot' => $slots['a'],
            'slot_or_none' => $slots['a'] ?? 'none',
        ];
    }

    /**
     * The `a` key of the array passed in.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function readA(array $data): array
    {
        return ['a' => $data['a'] ?? null];
    }

    /**
     * The post's id and title.
     *
     * @return array{int, string}
     */
    private function pair(): array
    {
        return [$this->id, (string) $this->title];
    }
}
