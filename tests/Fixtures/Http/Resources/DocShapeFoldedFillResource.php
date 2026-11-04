<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource folding spread helpers' `@return` fills, an override (`Person` as `User`) beside the model, into keys. */
final class DocShapeFoldedFillResource extends JsonResource
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
        $local = [...$this->documented()];

        return [
            'x' => $this->bare(),
            'direct' => [...$this->documented()],
            'nested' => ['y' => [...$this->documented()]],
            'beside' => [...$this->documented(), 'm' => 1],
            'local' => $local,
            'model_only' => [...$this->modelOnly()],
            'object_pair' => [...$this->objectPair()],
            'listed_literal' => [...$this->listedLiteral()],
            'object_or_null' => [...$this->objectOrNull()],
        ];
    }

    /**
     * A helper with no `@return`, read as a value, spreading the override-beside-model fill.
     */
    private function bare(): array
    {
        return [...$this->documented(), 'm' => 1];
    }

    /**
     * The override beside the model.
     *
     * @return array{k: Person|User}
     */
    private function documented(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }

    /**
     * The model alone.
     *
     * @return array{k: User}
     */
    private function modelOnly(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }

    /**
     * An object holding the override and the model.
     *
     * @return array{k: array{a: Person, b: User}}
     */
    private function objectPair(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }

    /**
     * A list of objects spelling `User` in a literal beside the model.
     *
     * @return array{k: list<array{kind: 'User', r: User}>}
     */
    private function listedLiteral(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }

    /**
     * An object spelling `User` in a literal beside the model, or null.
     *
     * @return array{k: array{kind: 'User', r: User}|null}
     */
    private function objectOrNull(): array
    {
        return ['k' => json_decode((string) $this->content)];
    }
}
