<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `toArray()` reaches helpers StaticSyntaxResource overrides through `self::`, `static::`
 * and `new self`, and a method only StaticSyntaxResource declares through `self::` and `static::`.
 */
class BaseStaticSyntaxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...self::helper(),
            ...static::lateHelper(),
            'built' => new self($this->resource)->label(),
            'forwarded' => self::publishable(),
            'late' => static::publishable(),
        ];
    }

    /** @return array<string, mixed> */
    public function label(): array
    {
        return ['base_label' => 1];
    }

    /** @return array<string, mixed> */
    protected function helper(): array
    {
        return ['base_helper' => 1];
    }

    /** @return array<string, mixed> */
    protected function lateHelper(): array
    {
        return ['base_late' => 1];
    }
}
