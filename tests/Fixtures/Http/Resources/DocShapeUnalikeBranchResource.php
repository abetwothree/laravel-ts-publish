<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource whose return branches spell an override-beside-model fill's names with other classes behind them. */
final class DocShapeUnalikeBranchResource extends JsonResource
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
            return [...$this->either()];
        }

        return [...$this->model(), 'plain' => $this->user, 'cast' => $this->user];
    }

    /**
     * Each key spells the override beside the model; a cast replaces one fill.
     *
     * @return array{plain: Person|User, declared: Person|User, cast: Person|User}
     */
    #[TsCasts(['cast' => 'string'])]
    private function either(): array
    {
        return [
            'plain' => json_decode((string) $this->content),
            'declared' => json_decode((string) $this->content),
            'cast' => json_decode((string) $this->content),
        ];
    }

    /**
     * The model alone, whose token reads another slot.
     *
     * @return array{declared: User}
     */
    private function model(): array
    {
        return ['declared' => json_decode((string) $this->content)];
    }
}
