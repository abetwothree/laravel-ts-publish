<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only model-backed resource whose `toArray()` reads a constant key and an untyped property LateReadsResource
 * redeclares, beside a private property it redeclares too.
 */
class BaseLateReadsResource extends JsonResource
{
    public const KEY = 'base_key';

    protected $tier = 'gold';

    private string $secret = 'x';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            static::KEY => 1,
            'tier' => $this->tier,
            'secret' => $this->secret,
        ];
    }
}
