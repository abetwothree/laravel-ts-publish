<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * An author, a title and a flag the value-spread probes read (GH-154).
 */
final class CreditLine
{
    public function __construct(
        public MailRecipient $author,
        public string $title = '',
        public bool $flag = false,
    ) {}
}
