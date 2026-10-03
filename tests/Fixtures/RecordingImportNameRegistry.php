<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use Override;

/** A test-only registry that overrides reserve() with the signature it has always had, and records each name. */
class RecordingImportNameRegistry extends ImportNameRegistry
{
    /** @var list<string> */
    public array $reservations = [];

    #[Override]
    public function reserve(string $localName): void
    {
        $this->reservations[] = $localName;

        parent::reserve($localName);
    }
}
