<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\BoardJson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Board;
use Workbench\App\Packages\Ui\Panel;

/**
 * Reads a value object that keeps its members' overrides through a docblock shape, a JsonSerializable's docblock shape
 * and an untyped helper, and a published model through a docblock shape and an untyped helper, which stays `unknown`.
 */
class UiBoardShapesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'doc_shape' => $this->shaped(),
            'json' => $this->json(),
            'untyped_helper' => $this->untypedHelper(),
            'doc_owner' => $this->documentedOwner(),
            'untyped_owner' => $this->untypedOwner(),
        ];
    }

    /**
     * A board and its label, by a docblock shape.
     *
     * @return array{board: Board, label: string}
     */
    protected function shaped(): array
    {
        return ['board' => new Board, 'label' => 'Board'];
    }

    /**
     * A board behind jsonSerialize().
     */
    protected function json(): BoardJson
    {
        return new BoardJson;
    }

    /**
     * A board and its label, with no declared return type.
     */
    protected function untypedHelper()
    {
        return ['board' => new Board, 'label' => 'Board'];
    }

    /**
     * An owner beside a panel, by a docblock shape.
     *
     * @return array{owner: User, panel: Panel}
     */
    protected function documentedOwner(): array
    {
        return ['owner' => $this->owner(), 'panel' => new Panel];
    }

    /**
     * An owner beside a panel, with no declared return type.
     */
    protected function untypedOwner()
    {
        return ['owner' => $this->owner(), 'panel' => new Panel];
    }

    /**
     * The board's owner.
     */
    protected function owner(): User
    {
        return new User()->forceFill(['name' => 'Ada']);
    }
}
