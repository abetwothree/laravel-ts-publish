<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;

/**
 * A test-only EnumResource subclass that adds conditional attributes, which Laravel's filter drops or merges.
 */
class ConditionalEnumResource extends EnumResource
{
    /** @return array<string, mixed>|null */
    public function toArray(Request $request): ?array
    {
        $data = parent::toArray($request);

        return $data === null ? null : [
            ...$data,
            'hidden' => $this->when(false, 'never sent'),
            'shown' => $this->when(true, 'sent'),
            $this->mergeWhen(true, ['merged' => 1]),
        ];
    }
}
