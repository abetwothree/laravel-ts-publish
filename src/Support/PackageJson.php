<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use Illuminate\Support\Facades\File;

/**
 * Reads the project's package.json to detect installed npm dependencies.
 *
 * Used to choose framework-specific packages — e.g. `@inertiaui/table-vue` vs
 * `@inertiaui/table-react`, or `@laravel/echo-vue` vs `@laravel/echo-react`.
 */
class PackageJson
{
    /**
     * Return the first candidate present in the project's package.json
     * dependencies or devDependencies, preserving candidate order.
     *
     * @param  list<string>  $candidates
     */
    public static function firstInstalled(array $candidates): ?string
    {
        $dependencies = self::dependencies();

        foreach ($candidates as $candidate) {
            if (isset($dependencies[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether the given package is declared in dependencies or devDependencies.
     */
    public static function has(string $package): bool
    {
        return isset(self::dependencies()[$package]);
    }

    /**
     * Merged dependencies + devDependencies from the project's package.json.
     *
     * Returns an empty array when package.json is missing or unreadable.
     *
     * @return array<string, string>
     */
    public static function dependencies(): array
    {
        $path = base_path('package.json');

        if (! File::exists($path)) {
            return [];
        }

        /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>}|null $packageJson */
        $packageJson = json_decode(File::get($path), true);

        if (! is_array($packageJson)) {
            return [];
        }

        return array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        );
    }
}
