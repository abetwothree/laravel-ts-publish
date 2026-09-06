<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Regression (Task 32 review, IMPORTANT-3): the model arm must include $appends, not just
 * database columns — Address::$appends declares 'full_address', which also carries its own
 * #[TsCasts] override that must still apply once flattened.
 */
class ModelArmAppendsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->primaryAddress->toArray(),
            'id' => $this->id,
        ];
    }
}
