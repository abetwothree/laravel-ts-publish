<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A value whose get hooks change what json_encode() writes: a hooked backed property and a virtual one. */
final class HookedPropertiesValue
{
    public string $code {
        get => strtoupper($this->code);
    }

    public string $label {
        get => 'dock-'.$this->code;
    }

    protected string $token = 'hidden';

    public function __construct(string $code)
    {
        $this->code = $code;
    }
}
