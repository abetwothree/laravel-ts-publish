<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/** A resource subject whose numeric constant key publishes as JSON writes it. Also the foreign class. */
final class ConstantKeyResource extends JsonResource
{
    public const int NUMERIC_KEY = 8;

    public const string EXTERNAL_KEY = 'external';

    /** Both constant keys are kept, the numeric one as `"8"`. */
    public function shape(): array
    {
        return [self::NUMERIC_KEY => 'kept', self::EXTERNAL_KEY => 'kept'];
    }
}
