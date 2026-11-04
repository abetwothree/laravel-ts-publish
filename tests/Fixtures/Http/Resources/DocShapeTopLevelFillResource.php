<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** A resource spreading at its top level the fills that DocShapeFoldedFillResource and its literal twin fold. */
final class DocShapeTopLevelFillResource extends JsonResource
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
        return [...$this->literal(), ...$this->pair(), ...$this->override()];
    }

    /**
     * The literal `'User'` beside the model.
     *
     * @return array{k: array{kind: 'User', r: User}}
     */
    private function literal(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }

    /**
     * The two same-basename users.
     *
     * @return array{p: User|CrmUser}
     */
    private function pair(): array
    {
        return ['p' => json_decode((string) $this->content)];
    }

    /**
     * The override beside the model.
     *
     * @return array{o: Person|User}
     */
    private function override(): array
    {
        return ['o' => json_decode((string) $this->content)];
    }
}
