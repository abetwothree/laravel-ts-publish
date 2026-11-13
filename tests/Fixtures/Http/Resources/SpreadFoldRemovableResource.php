<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource writing a key, then writing it again on every path with a when() value Laravel may remove.
 *
 * @mixin Post
 */
final class SpreadFoldRemovableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $whenLocal = ['local' => $this->when($request->boolean('picked'), $this->title)];

        return [
            'id' => $this->id,
            'author' => $this->author,
            ...$this->loadedAuthor(),
            'writer' => $this->author,
            ...$this->pickedWriter($request),
            'by' => $this->author,
            ...$this->whenTitled($request),
            ...$this->authored(),
            ...$this->whenHelperTitled($request),
            'local' => $this->author,
            ...$whenLocal,
        ];
    }

    /** @return array<string, mixed> */
    private function loadedAuthor(): array
    {
        return ['author' => $this->whenLoaded('author', fn () => new UserResource($this->author))];
    }

    /** @return array<string, mixed> */
    private function pickedWriter(Request $request): array
    {
        return ['writer' => $this->when($request->boolean('picked'), fn () => new UserResource($this->author))];
    }

    /** @return array<string, mixed> */
    private function whenTitled(Request $request): array
    {
        return ['by' => $this->when($request->boolean('picked'), $this->title)];
    }

    /** @return array<string, mixed> */
    private function authored(): array
    {
        return ['helper_by' => $this->author];
    }

    /** @return array<string, mixed> */
    private function whenHelperTitled(Request $request): array
    {
        return ['helper_by' => $this->when($request->boolean('picked'), $this->title)];
    }
}
