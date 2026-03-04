<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use Illuminate\Support\Str;

/**
 * @template TTransformable
 */
abstract class CoreTransformer
{
    /**
     * @param  class-string<TTransformable>  $findable
     */
    public function __construct(
        protected string $findable,
    ) {
        $this->transform();
    }

    /** @return static */
    abstract public function transform(): self;

    abstract public function filename(): string;

    /** @return array<string, mixed> */
    abstract public function data(): array;

    /**
     * Resolve an absolute file path to a path relative to the project root.
     * Falls back to a vendor-relative path for files outside base_path().
     */
    protected function resolveRelativePath(string $absolutePath): string
    {
        $basePath = base_path().DIRECTORY_SEPARATOR;

        if (str_starts_with($absolutePath, $basePath)) {
            return Str::after($absolutePath, $basePath);
        }

        // File is outside base_path() (e.g. vendor in a package development context)
        if (str_contains($absolutePath, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
            return 'vendor'.DIRECTORY_SEPARATOR.Str::after($absolutePath, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
        }

        return $absolutePath;
    }
}
