<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading locals that always or only maybe write a helper's keys again.
 *
 * @mixin Post
 */
final class SpreadValueTopResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $titled = ['by' => $this->title];
        $maybe = [];

        if ($request->boolean('titled')) {
            $maybe['about'] = $this->title;
            $maybe['note'] = $this->title;
        }

        return [...$this->authored(), ...$titled, ...$maybe];
    }

    /** @return array<string, mixed> */
    private function authored(): array
    {
        return ['id' => $this->id, 'by' => $this->author, 'about' => $this->author];
    }
}
