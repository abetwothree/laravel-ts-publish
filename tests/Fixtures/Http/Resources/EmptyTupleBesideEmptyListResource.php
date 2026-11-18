<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource sending a method documented as `list{}`, which publishes `[]`, beside an empty array literal,
 * through when()'s value, when()'s default and a ternary (GH-166).
 */
class EmptyTupleBesideEmptyListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $c = $request->boolean('c');

        return [
            'value' => $this->when($c, $this->none(), []),
            'default' => $this->when($c, [], $this->none()),
            'ternary' => $c ? $this->none() : [],
        ];
    }

    /**
     * Nothing, documented as the empty list shape.
     *
     * @return list{}
     */
    private function none(): array
    {
        return [];
    }
}
