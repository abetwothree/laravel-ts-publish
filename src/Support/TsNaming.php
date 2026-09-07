<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Composer\ClassMapGenerator\PhpFileParser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Turn a PHP name into a TypeScript name or an import path: resource type names, namespace paths,
 * relative imports, import ordering, key casing, and the class a file declares.
 *
 * @internal
 */
class TsNaming
{
    /**
     * Per-class cache of published resource interface names: FQCN => #[TsResource(name:)] or basename.
     *
     * @var array<string, string>
     */
    protected array $resourceTypeNames = [];

    /**
     * Resolve an absolute file path to a project-root-relative path, or a vendor-relative one.
     */
    public function resolveRelativePath(string $absolutePath): string
    {
        $basePath = base_path().DIRECTORY_SEPARATOR;

        if (str_starts_with($absolutePath, $basePath)) {
            return Str::after($absolutePath, $basePath);
        }

        // Outside base_path(), e.g. vendor in a package development context
        if (str_contains($absolutePath, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
            return 'vendor'.DIRECTORY_SEPARATOR.Str::after($absolutePath, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
        }

        return $absolutePath;
    }

    /**
     * Recase an emitted object key: camel, snake or pascal, or unchanged for anything else.
     */
    public function keyCase(string $key, string $case): string
    {
        return match ($case) {
            'camel' => Str::camel($key),
            'snake' => Str::snake($key),
            'pascal' => Str::studly($key),
            default => $key,
        };
    }

    /**
     * Resolve the TypeScript interface name a resource class is published under.
     *
     * #[TsResource(name:)] renames the emitted interface, so a reference that used class_basename()
     * instead named a type nothing declares. ResourceTransformer::initReflection() is the same rule.
     */
    public function resourceTypeName(string $fqcn): string
    {
        if (isset($this->resourceTypeNames[$fqcn])) {
            return $this->resourceTypeNames[$fqcn];
        }

        $name = class_basename($fqcn);

        if (class_exists($fqcn)) {
            $attributes = (new ReflectionClass($fqcn))->getAttributes(TsResource::class);

            if ($attributes !== []) {
                $name = $attributes[0]->newInstance()->name ?? $name;
            }
        }

        return $this->resourceTypeNames[$fqcn] = $name;
    }

    /**
     * Convert a FQCN to a modular output directory path.
     *
     * Example: 'Blog\Enums\ArticleStatus' → 'blog/enums'
     */
    public function namespaceToPath(string $fqcn): string
    {
        $namespace = Str::beforeLast($fqcn, '\\');

        $prefix = Config::string('ts-publish.namespace_strip_prefix', '');

        if ($prefix !== '' && str_starts_with($namespace, $prefix)) {
            $namespace = substr($namespace, strlen($prefix));
        }

        return collect(explode('\\', $namespace))
            ->filter()
            ->map(fn (string $segment) => Str::kebab($segment))
            ->implode('/');
    }

    /**
     * Compute the TypeScript relative import path from one namespace path to another.
     *
     * Example: 'blog/models' → 'blog/enums' = '../enums'; 'models' → 'models/videos' = './videos'
     *
     * An empty from-path is the output root, not a directory named '' — one segment deep would climb out.
     */
    public function relativeImportPath(string $fromNamespacePath, string $toNamespacePath): string
    {
        if ($fromNamespacePath === $toNamespacePath) {
            return '.';
        }

        $fromParts = $fromNamespacePath === '' ? [] : explode('/', $fromNamespacePath);
        $toParts = explode('/', $toNamespacePath);

        $commonLength = 0;
        $maxCommon = min(count($fromParts), count($toParts));

        while ($commonLength < $maxCommon && $fromParts[$commonLength] === $toParts[$commonLength]) {
            $commonLength++;
        }

        $upCount = count($fromParts) - $commonLength;
        $downSegments = array_slice($toParts, $commonLength);

        // TypeScript reads a bare specifier like 'videos' as a module lookup, not a relative
        // path, so a descendant target must be prefixed with './'.
        if ($upCount === 0) {
            return './'.implode('/', $downSegments);
        }

        $relative = str_repeat('../', $upCount).implode('/', $downSegments);

        return rtrim($relative, '/');
    }

    /**
     * Sort import paths following eslint-plugin-simple-import-sort conventions: packages, then
     * absolute/other, then relative (deeper first), alphabetical (case-insensitive) within a group.
     *
     * @param  array<string, list<string>>  $imports
     * @return array<string, list<string>>
     */
    public function sortImportPaths(array $imports): array
    {
        uksort($imports, function (string $a, string $b): int {
            $groupA = $this->importSortGroup($a);
            $groupB = $this->importSortGroup($b);

            if ($groupA !== $groupB) {
                return $groupA <=> $groupB;
            }

            // Within relative imports, deeper paths come first
            if ($groupA === 2) {
                $depthA = count(array_filter(explode('/', $a), fn (string $s): bool => $s === '..'));
                $depthB = count(array_filter(explode('/', $b), fn (string $s): bool => $s === '..'));

                if ($depthA !== $depthB) {
                    return $depthB <=> $depthA;
                }
            }

            return strnatcasecmp($a, $b);
        });

        return $imports;
    }

    /**
     * Resolve the fully-qualified class name from a PHP file path.
     *
     * Returns null if the file does not exist or does not contain a class/enum declaration.
     */
    public function resolveClassFromFile(string $filePath): ?string
    {
        $absolutePath = str_starts_with($filePath, DIRECTORY_SEPARATOR)
            ? $filePath
            : base_path($filePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $classes = PhpFileParser::findClasses($absolutePath);

        return $classes[0] ?? null;
    }

    /**
     * Determine the sort group for an import path: 0 = package, 1 = absolute/other, 2 = relative.
     */
    protected function importSortGroup(string $path): int
    {
        if (str_starts_with($path, '.')) {
            return 2;
        }

        if (preg_match('/^@?\w/', $path)) {
            return 0;
        }

        return 1;
    }
}
