<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * A model-backed resource with an int key and a numeric-string key, which PHP stores as an int.
 *
 * @mixin Team
 */
final class NumericKeyTeamResource extends JsonResource
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
            5 => 'int-key',
            '6' => 'string-key',
        ];
    }
}
