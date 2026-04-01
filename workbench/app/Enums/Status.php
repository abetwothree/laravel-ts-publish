<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;
use ArchTech\Enums\Names;
use ArchTech\Enums\Options;
use ArchTech\Enums\Values;
use Illuminate\Support\Str;

enum Status: int
{
    use Names {
        names as parentNames;
    }
    use Options {
        options as parentOptions;
    }
    use Values {
        values as parentValues;
    }

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

    #[TsEnumMethod]
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'green',
        };
    }

    #[TsEnumStaticMethod(description: 'Get the key-value pair options for the status')]
    public static function valueLabelPair(): array
    {
        return collect(self::cases())->map(fn ($case) => [
            'label' => Str::title($case->name),
            'value' => $case->value,
        ])
            ->values()
            ->toArray();
    }

    #[TsEnumStaticMethod]
    public static function names(): array
    {
        return self::parentNames();
    }

    #[TsEnumStaticMethod]
    public static function values(): array
    {
        return self::parentValues();
    }

    #[TsEnumStaticMethod]
    public static function options(): array
    {
        return self::parentOptions();
    }
}
