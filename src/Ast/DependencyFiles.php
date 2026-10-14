<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use Composer\Autoload\ClassLoader;
use ReflectionClass;

/**
 * The files of an application's dependencies, whose methods no value read follows: those under a vendor directory
 * Composer registered, so a custom `vendor-dir` counts and an application root inside a `vendor` folder does not, and
 * this package's own `src/`, which an application installs there too.
 *
 * @internal
 */
final readonly class DependencyFiles
{
    /**
     * Hold the directories a dependency's file sits under.
     *
     * @param  list<string>  $directories  each ending in a directory separator
     */
    private function __construct(private array $directories) {}

    /**
     * The running application's dependencies, from the vendor directory of each autoloader Composer registered.
     */
    public static function ofApplication(): self
    {
        return self::withVendorDirectories(array_keys(ClassLoader::getRegisteredLoaders()));
    }

    /**
     * The dependencies under the given vendor directories, else the one Composer's own loader sits in.
     *
     * @param  list<string>  $vendorDirectories
     */
    public static function withVendorDirectories(array $vendorDirectories): self
    {
        if ($vendorDirectories === []) {
            $loader = new ReflectionClass(ClassLoader::class)->getFileName();
            $vendorDirectories = $loader === false ? [] : [dirname($loader, 2)];
        }

        return new self(array_map(
            fn (string $directory): string => rtrim($directory, '/\\').DIRECTORY_SEPARATOR,
            [...$vendorDirectories, dirname(__DIR__)],
        ));
    }

    /**
     * Whether a file is a dependency's; a built-in method, which has none, is one too.
     */
    public function contains(string|false $file): bool
    {
        return $file === false
            || array_any($this->directories, fn (string $directory): bool => str_starts_with($file, $directory));
    }
}
