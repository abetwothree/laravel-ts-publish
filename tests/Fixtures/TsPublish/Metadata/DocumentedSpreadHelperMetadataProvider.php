<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

/** Spreads a helper whose own `@return` types the member a cache read leaves unknown (GH-154). */
final class DocumentedSpreadHelperMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata a documented helper builds.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [...$this->recipientProps()];
    }

    /**
     * The recipients and each one's cached settings.
     *
     * @return array{recipients: list<array{name: string, settings: array{digest: bool}|null}>}
     */
    private function recipientProps(): array
    {
        return ['recipients' => array_map(fn ($n) => ['name' => $n, 'settings' => cache('mail.'.$n)], ['a', 'b'])];
    }
}
