<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A `_tag` signature its model casts optional.
 *
 * @mixin OptionalSignatureCastModel
 */
final class OptionalSignatureModelCastResource extends JsonResource
{
    /** Keys built from literal text around a variable. */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = 'Tag';
        }

        return $data;
    }
}
