<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;

trait IncludesExtras
{
    /**
     * @return array{id: string, computed: string, date_val: datetime, custom_val: CustomObject, int}
     */
    protected function includeTypedExtras(): array
    {
        return [
            'id' => $this->id,
            'computed' => strtoupper('x'),
            'date_val' => now(),
            'custom_val' => json_decode('{}'), // json_decode() returns mixed (resolves to unknown) → body stays unknown; docblock CustomObject kicks in
        ];
    }

    protected function includeNoDocs(): array
    {
        return ['plain' => strtoupper('test')]; // strtoupper resolves to string (no docblock to override)
    }

    /**
     * Some description.
     */
    protected function includeNoShape(): array
    {
        return ['basic' => strtolower('test')]; // strtolower resolves to string (description-only docblock, no array shape)
    }

    /**
     * @return array{
     *     firstName: string,
     *     lastName: string,
     *     isActive: bool,
     * }
     */
    protected function includeMultilineShape(): array
    {
        return [
            'firstName' => strtoupper('x'),
            'lastName' => strtoupper('y'),
            'isActive' => true,
        ];
    }

    /** @param array<string, string|array{type: string, import?: string, optional?: bool}> $types */
    #[TsResourceCasts([
        'location' => ['type' => 'GeoPoint', 'import' => '@/types/geo'],
        'flag' => ['type' => 'string | null', 'optional' => true],
        'extra' => 'Record<string, unknown>',
    ])]
    protected function includeCastedExtras(): array
    {
        return [
            'location' => strtoupper('x'),
            'flag' => strtolower('y'),
        ];
    }
}
