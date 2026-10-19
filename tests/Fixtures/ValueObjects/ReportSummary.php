<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * An Arrayable whose toArray() differs from its public properties, which json_encode() writes instead.
 *
 * @implements Arrayable<string, int>
 */
final class ReportSummary implements Arrayable
{
    public string $label = 'weekly';

    /**
     * The shape Inertia sends, where a resource or an event sends the public properties.
     *
     * @return array{total: int}
     */
    public function toArray(): array
    {
        return ['total' => 3];
    }
}
