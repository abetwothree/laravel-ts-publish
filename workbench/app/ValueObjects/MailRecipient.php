<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * One person a message goes to: a plain value object, not a model.
 */
final readonly class MailRecipient
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {}
}
