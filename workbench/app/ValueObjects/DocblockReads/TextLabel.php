<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

/**
 * A label whose text is private (GH-134).
 */
final class TextLabel implements HasLabel
{
    public function __construct(private string $text) {}

    /**
     * The label.
     */
    public function label(): string
    {
        return $this->text;
    }
}
