<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading locals before and after a helper's or a key's `by`, nested in a value.
 *
 * @mixin Post
 */
final class SpreadValueOverrideResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $titled = ['by' => $this->title];
        $extra = ['extra' => $this->title];
        $maybe = [];

        if ($request->boolean('titled')) {
            $maybe['by'] = $this->title;
        }

        return [
            'helper_then_value' => [...$this->authored(), ...$titled],
            'key_then_value' => ['by' => $this->author, ...$titled],
            'helper_then_maybe' => [...$this->authored(), ...$maybe],
            'helper_then_ternary' => [...$this->authored(), ...($request->boolean('titled') ? $titled : [])],
            'helper_then_new_key' => [...$this->authored(), ...$extra],
            'value_then_helper' => [...$titled, ...$this->authored()],
            'value_then_key' => [...$titled, 'by' => $this->author],
        ];
    }

    /** @return array<string, mixed> */
    private function authored(): array
    {
        return ['id' => $this->id, 'by' => $this->author];
    }
}
