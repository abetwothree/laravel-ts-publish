<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading a string map after keys, at the top level and nested in a value.
 *
 * @mixin Post
 */
final class SpreadValueMapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, string> $labels */
        $labels = $request->input('labels', []);

        return [
            ...$this->authored(),
            ...$labels,
            'row' => [...$this->authored(), ...$labels],
            'tones' => ['tone' => $this->title, ...$labels],
        ];
    }

    /** @return array<string, mixed> */
    private function authored(): array
    {
        return ['id' => $this->id, 'by' => $this->author];
    }
}
