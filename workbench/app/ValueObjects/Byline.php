<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Who wrote a piece and the title it carries (GH-154).
 */
final class Byline
{
    public function __construct(
        public MailRecipient $author,
        public string $title = '',
    ) {}
}
