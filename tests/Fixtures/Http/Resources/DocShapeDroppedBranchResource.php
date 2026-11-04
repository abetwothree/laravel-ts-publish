<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource whose spread helper and merge closure each join an override-beside-model fill with an untyped branch. */
final class DocShapeDroppedBranchResource extends JsonResource
{
    /** @var Comment */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->outer(),
            $this->merge(function () {
                if ($this->id > 1) {
                    return $this->merged();
                }

                return ['merged' => json_decode((string) $this->content)];
            }),
        ];
    }

    /**
     * A spread helper whose other branch types nothing.
     *
     * @return array<string, mixed>
     */
    private function outer(): array
    {
        if ($this->id > 1) {
            return [...$this->inner()];
        }

        return ['spread' => json_decode((string) $this->content)];
    }

    /**
     * The spread helper's fill.
     *
     * @return array{spread: Person|User}
     */
    private function inner(): array
    {
        return ['spread' => json_decode((string) $this->content)];
    }

    /**
     * The merge closure's fill.
     *
     * @return array{merged: Person|User}
     */
    private function merged(): array
    {
        return ['merged' => json_decode((string) $this->content)];
    }
}
