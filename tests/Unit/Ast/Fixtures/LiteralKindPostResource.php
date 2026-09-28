<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** toArray()'s own `@return` and a spread helper's type untyped values as string literals that spell class names. */
final class LiteralKindPostResource extends JsonResource
{
    /**
     * The keys, each over a value the body cannot type.
     *
     * @return array{kind: 'User'|'Comment', status: 'Status', note: 'User\'s Post', spread_kind?: 'Post'|'Image'}
     */
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->opaque(),
            'status' => $this->opaque(),
            'note' => $this->opaque(),
            ...$this->extra(),
        ];
    }

    /**
     * The spread keys.
     *
     * @return array{spread_kind: 'Post'|'Image'}
     */
    protected function extra(): array
    {
        return ['spread_kind' => $this->opaque()];
    }

    /** Deliberately untyped. */
    private function opaque()
    {
        return json_decode('{}');
    }
}
