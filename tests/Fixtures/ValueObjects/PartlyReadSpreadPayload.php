<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose helpers, read as values, spread its parent's partly read methods through `parent::`. */
final class PartlyReadSpreadPayload extends PartlyReadSpreadBase
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['merged' => $this->mergedPart(), 'picked' => $this->pickedPart(), 'first' => $this->firstPart()];
    }

    /** @return array<string, mixed> */
    public function mergedPart(): array
    {
        return ['k' => 1, ...parent::merged()];
    }

    /** @return array<string, mixed> */
    public function pickedPart(): array
    {
        return ['k' => 1, ...parent::picked()];
    }

    /** @return array<string, mixed> */
    public function firstPart(): array
    {
        return ['k' => 1, ...parent::firstOnly()];
    }
}
