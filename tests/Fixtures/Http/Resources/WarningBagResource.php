<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WarningBag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Reads a promoted property its constructor documents, and returns the value object whole. */
final class WarningBagResource extends JsonResource
{
    /**
     * The bag's warnings, and the bag.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'warnings' => $this->bag()->warnings,
            'bag' => $this->bag(),
        ];
    }

    /** The bag the keys read. */
    private function bag(): WarningBag
    {
        return new WarningBag(['late'], ['attempt' => 1], [404]);
    }
}
