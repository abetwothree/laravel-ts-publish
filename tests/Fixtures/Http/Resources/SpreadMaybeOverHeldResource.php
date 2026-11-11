<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource writing held keys again through a helper, a when() value or a mergeWhen() that may not land.
 *
 * @mixin Post
 */
final class SpreadMaybeOverHeldResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->authored(),
            ...$this->maybeTitled($request),
            'key' => $this->author,
            ...$this->maybeKeyed($request),
            'when' => $this->author,
            ...$this->whenTitled($request),
            $this->mergeWhen($request->boolean('picked'), ['merged' => $this->title]),
            'merged' => $this->author,
        ];
    }

    /** @return array<string, mixed> */
    private function authored(): array
    {
        return ['id' => $this->id, 'by' => $this->author];
    }

    /** @return array<string, mixed> */
    private function maybeTitled(Request $request): array
    {
        $out = [];

        if ($request->boolean('picked')) {
            $out['by'] = $this->title;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function maybeKeyed(Request $request): array
    {
        $out = [];

        if ($request->boolean('picked')) {
            $out['key'] = $this->title;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function whenTitled(Request $request): array
    {
        return ['when' => $this->when($request->boolean('picked'), $this->title)];
    }
}
