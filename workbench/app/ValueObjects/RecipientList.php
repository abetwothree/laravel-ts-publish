<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * The people a message goes to, held once as a promoted property and once as a plain one, each a docblock list of
 * MailRecipient (GH-154).
 */
final class RecipientList
{
    /** @var list<MailRecipient> */
    public array $copied = [];

    public function __construct(
        /** @var list<MailRecipient> */
        public array $recipients = [],
    ) {}
}
