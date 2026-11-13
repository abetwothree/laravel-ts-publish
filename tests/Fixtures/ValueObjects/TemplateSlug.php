<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use JsonSerializable;

/** A value whose override is a template literal holding `| null |`, which names no null arm. */
#[TsType('`a | null | b`')]
final class TemplateSlug implements JsonSerializable
{
    /** Sends the slug text, which the template literal matches. */
    public function jsonSerialize(): string
    {
        return 'a | null | b';
    }
}
