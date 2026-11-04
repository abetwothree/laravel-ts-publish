<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource folding a model fill one or more levels away from an override (`Person` as `User`) in the same key. */
final class DocShapeFoldedCousinResource extends JsonResource
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
            'in_place_cousin' => ['n' => $this->person(), 'inner' => [...$this->userAlone()]],
            'fill_cousins' => ['p' => [...$this->personAlone()], 'q' => [...$this->userAlone()]],
            'deep_cousin' => ['p' => ['n' => $this->person()], 'q' => [...$this->userAlone()]],
            'listed' => [[...$this->personAlone()], [...$this->userAlone()]],
            'literal_cousin' => ['n' => $this->person(), 'lm' => [...$this->literalUser()]],
        ];
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
     * The literal `'User'` beside the model.
     *
     * @return array{kind: 'User', m: User}
     */
    private function literalUser(): array
    {
        $decoded = json_decode((string) $this->content);

        return ['kind' => $decoded, 'm' => $decoded];
    }

    /**
     * The override, written in place.
     */
    private function person(): Person
    {
        return new Person;
    }
}
