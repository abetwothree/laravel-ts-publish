<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\Spreads;

/**
 * A final class returning typed maps, one from a literal body and one from a static property (GH-161 C2).
 */
final class SeedInner
{
    /** @var array<string, string> a static string map */
    public static array $presets = ['k' => 'v'];

    /**
     * A string map read off a static property.
     *
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return self::$presets;
    }

    /**
     * A string map whose body is one literal.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return ['k' => 'v'];
    }
}
