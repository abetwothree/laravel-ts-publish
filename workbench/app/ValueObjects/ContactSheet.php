<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Contacts beside values the engine cannot type (GH-154): extra keys held in an array with no element type, a hint
 * with no type at all, and a title.
 */
final class ContactSheet
{
    public array $extras = [];

    public $hint;

    public ?string $title = null;

    public function __construct(
        /** @var list<MailRecipient> */
        public array $contacts = [],
    ) {}
}
