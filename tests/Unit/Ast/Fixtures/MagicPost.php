<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Sends an outside read of a name it does not expose to __get(), so only its public instance property is read. */
final class MagicPost
{
    public static string $kind = 'post';

    public string $title = '';

    protected string $status = 'draft';

    /** @var array<string, mixed> */
    private array $data = [];

    /** Every outside read of a non-public or undeclared name. */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }
}
