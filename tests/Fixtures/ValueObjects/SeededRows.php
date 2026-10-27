<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * A payload whose map callback writes a key into each element it is passed and returns it.
 */
final class SeededRows
{
    /** @var list<array{id: int}> */
    public array $rows = [['id' => 1]];

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rows' => collect($this->rows)->map(function (array $row) {
                $row['seen'] = true;

                return $row;
            })->all(),
        ];
    }
}
