<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource folding fills whose override (`Person` as `User`) and model sit in sibling members of one object. */
final class DocShapeFoldedSplitResource extends JsonResource
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
            'one_fill' => [...$this->split()],
            'two_fills' => [...$this->personAlone(), ...$this->userAlone()],
            'with_null' => [...$this->splitWithNull()],
            'x_split' => $this->bareSplit(),
        ];
    }

    /**
     * The override and the model in sibling members.
     *
     * @return array{n: Person, m: User}
     */
    private function split(): array
    {
        return ['n' => json_decode((string) $this->content), 'm' => json_decode((string) $this->content)];
    }

    /**
     * The override alone.
     *
     * @return array{n: Person}
     */
    private function personAlone(): array
    {
        return ['n' => json_decode((string) $this->content)];
    }

    /**
     * The model alone.
     *
     * @return array{m: User}
     */
    private function userAlone(): array
    {
        return ['m' => json_decode((string) $this->content)];
    }

    /**
     * The override beside the model in one member, and each alone in a sibling.
     *
     * @return array{k: Person|User, n: Person, m: User}
     */
    private function splitWithNull(): array
    {
        $decoded = json_decode((string) $this->content);

        return ['k' => $decoded, 'n' => $decoded, 'm' => $decoded];
    }

    /**
     * A helper with no `@return`, read as a value, spreading the split fill.
     */
    private function bareSplit(): array
    {
        return [...$this->split(), 'z' => 1];
    }
}
