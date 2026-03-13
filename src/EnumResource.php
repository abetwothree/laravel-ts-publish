<?php

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Transformers\EnumInstanceTranformer;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use UnitEnum;

/**
 * @phpstan-import-type EnumInstanceData from EnumInstanceTranformer
 *
 * Transform an enum from model property to an API response.
 */
class EnumResource extends JsonResource
{
    /** @var UnitEnum|BackedEnum */
    public $resource;

    public static $wrap = '';

    /** @return EnumInstanceData */
    public function toArray(Request $request): array
    {
        /** @var class-string<UnitEnum|BackedEnum> $enumClass */
        $enumClass = $this->resource::class;

        return (new EnumInstanceTranformer(
            new EnumTransformer($enumClass)->data(),
            $this->resource,
        ))->data();
    }
}
