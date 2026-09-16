<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/** A resource subject, where a numeric key cannot become a published member. Also the foreign class. */
final class ConstantKeyResource extends JsonResource
{
    public const int NUMERIC_KEY = 8;

    public const string EXTERNAL_KEY = 'external';

    /** The numeric constant key is dropped because the subject is a resource; the string-valued one survives. */
    public function shape(): array
    {
        return [self::NUMERIC_KEY => 'dropped', self::EXTERNAL_KEY => 'kept'];
    }
}
