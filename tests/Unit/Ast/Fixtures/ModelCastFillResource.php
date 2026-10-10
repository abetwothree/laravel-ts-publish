<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A docblock-filled signature beside a same-pattern key only the model's cast types.
 *
 * @mixin TagCastPost
 */
final class ModelCastFillResource extends JsonResource
{
    /** Spreads the filled signature beside the untyped key. */
    public function toArray(Request $request): array
    {
        return [...$this->docTags(), 'main_tag' => $this->opaque()];
    }

    /**
     * `_tag` keys only the docblock types.
     *
     * @return array<string, string>
     */
    public function docTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->opaque();
        }

        return $data;
    }

    /** Deliberately untyped. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}
