<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading a helper and a literal in each order beside a key, at the top level and nested in a
 * value: PHP keeps each key's last write.
 *
 * @mixin Post
 */
final class SpreadOverrideOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->authored(),
            ...['by' => $this->title],
            'helper_then_literal' => [...$this->authored(), ...['by' => $this->title]],
            'literal_then_helper' => [...['by' => $this->title], ...$this->authored()],
            'two_helpers' => [...$this->authored(), ...$this->titled()],
            'helper_then_key' => [...$this->authored(), 'by' => $this->title],
            'key_then_literal' => ['by' => $this->author, ...['by' => $this->title]],
            'literal_then_key' => [...['by' => $this->title], 'by' => $this->author],
        ];
    }

    /** @return array<string, mixed> */
    private function authored(): array
    {
        return ['id' => $this->id, 'by' => $this->author];
    }

    /** @return array<string, mixed> */
    private function titled(): array
    {
        return ['by' => $this->title];
    }
}
