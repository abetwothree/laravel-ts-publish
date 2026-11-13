<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

/** Metadata whose provide() types its own key and spreads a helper typing one of its own. */
final class GapModeMetadataProvider implements ModelMetadataProvider
{
    /**
     * A typed key beside a documented helper's keys.
     *
     * @return array{provided: string}
     */
    public function provide(Model $model): array
    {
        return ['provided' => $this->providedRead(), ...$this->providedPart()];
    }

    /**
     * A typed key beside an untyped one.
     *
     * @return array{part: string}
     */
    private function providedPart(): array
    {
        return ['part' => $this->partRead(), 'loose' => $this->looseRead()];
    }

    /**
     * provide()'s own value.
     */
    private function providedRead(): string
    {
        return 'provided';
    }

    /**
     * The helper's typed value.
     */
    private function partRead(): string
    {
        return 'part';
    }

    /**
     * The helper's untyped value.
     */
    private function looseRead(): string
    {
        return 'loose';
    }
}
