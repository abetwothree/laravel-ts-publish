<?php

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

enum Status: int
{
    case Draft = 0;
    case Published = 1;

    #[TsEnumMethod(description: 'Get the icon name for the status')]
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'pencil',
            self::Published => 'check',
        };
    }

    #[TsEnumStaticMethod(description: 'Get the key-value pair options for the status')]
    public static function keyValuePair(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->name => $case->value])->toArray();
    }
}
