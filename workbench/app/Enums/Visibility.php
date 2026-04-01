<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;

/**
 * Pure unit enum (no backing type) — tests that the publisher handles unit enums
 * where case names become the values in TypeScript.
 */
enum Visibility
{
    case Public;
    case Private;
    case Protected;
    case Internal;
    case Draft;

    #[TsEnumMethod(description: 'Whether the item is publicly accessible')]
    public function isPublic(): bool
    {
        return $this === self::Public;
    }

    #[TsEnumMethod(description: 'Description of the visibility level')]
    public function description(): string
    {
        return match ($this) {
            self::Public => 'Visible to everyone',
            self::Private => 'Only visible to the owner',
            self::Protected => 'Visible to team members',
            self::Internal => 'Visible to organization members',
            self::Draft => 'Not visible to anyone except the author',
        };
    }
}
