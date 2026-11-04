<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource whose spread helper joins a fill spelling `User` in a literal before an override-beside-model fill. */
final class DocShapeDroppingLiteralResource extends JsonResource
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
        return [...$this->outer()];
    }

    /**
     * A spread helper whose branches spread the two fills.
     *
     * @return array<string, mixed>
     */
    private function outer(): array
    {
        if ($this->id > 1) {
            return [...$this->literal()];
        }

        return [...$this->listed()];
    }

    /**
     * The literal's fill.
     *
     * @return array{k: array{kind: 'User'}}
     */
    private function literal(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }

    /**
     * The override-beside-model fill.
     *
     * @return array{k: list<Person>|User}
     */
    private function listed(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }
}
