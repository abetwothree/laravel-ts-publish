<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use Illuminate\Support\Str;
use Override;

/** A custom transformer_class whose naming scheme is a prefix, so it overrides the pair of methods rather than the suffix. */
final class PrefixedModelMetadataTransformer extends ModelMetadataTransformer
{
    public const string FILENAME_PREFIX = 'meta.';

    /**
     * Companion filename for a model class, without its TypeScript extension.
     */
    #[Override]
    public static function filenameFor(string $modelClass): string
    {
        return self::FILENAME_PREFIX.Str::kebab(class_basename($modelClass));
    }

    /**
     * Whether a barrel export names a metadata companion rather than a model interface.
     */
    #[Override]
    public static function isMetadataFilename(string $filename): bool
    {
        return str_starts_with($filename, self::FILENAME_PREFIX);
    }
}
