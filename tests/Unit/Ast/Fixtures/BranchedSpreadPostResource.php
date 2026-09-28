<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A spread helper with several returns, some of whose values the body cannot type.
 *
 * @mixin Post
 */
final class BranchedSpreadPostResource extends JsonResource
{
    /**
     * The keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...$this->label(),
        ];
    }

    /**
     * Each key's branches: typed and untypable, untypable twice, and untypable against null.
     *
     * @return array<string, mixed>
     */
    protected function label(): array
    {
        if ($this->title !== '') {
            return ['label' => $this->title, 'opaque' => $this->opaque(), 'nothing' => $this->opaque()];
        }

        return ['label' => $this->opaque(), 'opaque' => $this->opaque(), 'nothing' => null];
    }

    /** Deliberately untyped. */
    private function opaque()
    {
        return config('app.name');
    }
}
