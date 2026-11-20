<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers\Concerns;

use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;

/**
 * @property Filesystem $filesystem
 */
trait WarnsOfTemplatesWithoutVariants
{
    /**
     * Whether a resource prints a block that a template with no variants leaves out: its arms or its intersections.
     */
    protected function printsVariants(TsResourceDto|ResourceTransformer $resource): bool
    {
        return $resource->typeAlias === null && ($resource->variants !== [] || $resource->intersections !== []);
    }

    /**
     * Warn once per file of a template outside the package's own views that prints no variants.
     *
     * Laravel finds a published copy before the package's own, so the configured template name cannot reveal one.
     */
    protected function warnOfTemplateWithoutVariants(string $template): void
    {
        $path = realpath(View::getFinder()->find($template));
        $package = implode(DIRECTORY_SEPARATOR, [dirname(__DIR__, 3), 'resources', 'views', '']);

        if ($path === false || str_starts_with($path, $package) || str_contains($this->filesystem->get($path), 'variants')) {
            return;
        }

        AnalysisWarnings::addOnce($path, 'Prints no variants, so a resource with arms or a #[TsIntersect] type publishes '
            .'through it without them. Re-publish it with `php artisan vendor:publish --tag=laravel-ts-publish-views '
            .'--force`, or copy in the variants block of the package\'s own template.');
    }
}
