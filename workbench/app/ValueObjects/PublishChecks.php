<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Fixture: a value object a resource holds beside its model, whose methods share names with the model's own, so a call
 * on it proves the receiver's class answers and the model's namesake never does.
 */
final readonly class PublishChecks
{
    /** @return list<array{message: string, route?: string}> */
    public function toArray(): array
    {
        return [];
    }

    /** Post::commentsCount() returns an int. */
    public function commentsCount(): string
    {
        return 'none';
    }

    public static function label(): string
    {
        return 'checks';
    }
}
