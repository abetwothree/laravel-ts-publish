<?php

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Transformers\EnumInstanceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use UnitEnum;

/**
 * @phpstan-import-type EnumInstanceData from EnumInstanceTransformer
 *
 * Transform an enum from model property to an API response.
 */
class EnumResource extends JsonResource
{
    /** @var UnitEnum|BackedEnum|null */
    public $resource;

    public static $wrap = '';

    /** @return EnumInstanceData|null */
    public function toArray(Request $request): ?array
    {
        if ($this->resource === null) {
            return null;
        }

        /** @var class-string<UnitEnum|BackedEnum> $enumClass */
        $enumClass = $this->resource::class;

        return (new EnumInstanceTransformer(
            new EnumTransformer($enumClass)->data(),
            $this->resource,
        ))->data();
    }
}
