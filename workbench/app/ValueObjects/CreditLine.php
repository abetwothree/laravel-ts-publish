<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * An author, a title and a flag the value-spread probes read (GH-154).
 */
final class CreditLine
{
    /** @var array<string, string> a string map a spread writes */
    public array $labels = [];

    /** @var array<string, int> a number map a spread writes after it */
    public array $counts = [];

    public function __construct(
        public MailRecipient $author,
        public string $title = '',
        public bool $flag = false,
    ) {}
}
