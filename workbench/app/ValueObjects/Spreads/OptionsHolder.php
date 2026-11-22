<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\Spreads;

/**
 * An id, a flag and three nullable option arrays the merge-closure and collect() probes read (GH-121, GH-153).
 */
final class OptionsHolder
{
    /** @var array<string, string>|null a string map, or null */
    public ?array $record = null;

    /** @var list<string>|null a list, or null */
    public ?array $list = null;

    /** @var array{x: string}|null a closed shape, or null */
    public ?array $shape = null;

    public function __construct(
        public int $id = 0,
        public bool $flag = false,
    ) {}
}
