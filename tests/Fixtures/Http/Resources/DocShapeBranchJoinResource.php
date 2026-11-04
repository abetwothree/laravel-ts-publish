<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource whose return branches join a spread helper's override-beside-model fill with a string and a null. */
final class DocShapeBranchJoinResource extends JsonResource
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
        if ($this->id > 1) {
            return [...$this->shaped()];
        }

        return ['text' => 'x', 'none' => null];
    }

    /**
     * Both keys spell the override beside the model.
     *
     * @return array{text: Person|User, none: Person|User}
     */
    private function shaped(): array
    {
        return ['text' => json_decode((string) $this->content), 'none' => json_decode((string) $this->content)];
    }
}
