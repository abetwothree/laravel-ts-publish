<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Writes an echo-broadcast-events.d.ts module augmentation file for Laravel Echo.
 *
 * Augments the Echo 'Events' interface with all broadcast event types so that
 * channel listeners (Echo.listen(), useEcho()) are type-safe.
 *
 * Returns an empty string when echo_augmentation is disabled or no events are provided.
 */
class BroadcastEventsEchoWriter
{
    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    /**
     * Render and optionally write the Echo module augmentation file.
     *
     * Returns an empty string when Echo augmentation is disabled in config
     * or when no generators are provided.
     *
     * @param  Collection<int, BroadcastEventGenerator>  $generators
     */
    public function write(Collection $generators): string
    {
        if (! config()->boolean('ts-publish.broadcast_events.echo_augmentation.enabled')) {
            return '';
        }

        if ($generators->isEmpty()) {
            return '';
        }

        $echoPackage = $this->resolveEchoPackage();

        $content = $this->renderContent($generators, $echoPackage);

        if (config()->boolean('ts-publish.output_to_files')) {
            $this->writeFile($content);
        }

        return $content;
    }

    /**
     * Build the template data and render.
     *
     * @param  Collection<int, BroadcastEventGenerator>  $generators
     */
    protected function renderContent(Collection $generators, string $echoPackage): string
    {
        $events = $generators->map(function (BroadcastEventGenerator $generator): array {
            $transformer = $generator->transformer;
            $dto = $transformer->data();
            $relativePath = './'.$transformer->namespacePath.'/'.$transformer->filename();

            return [
                'eventName' => $dto->eventName,
                'broadcastName' => $dto->broadcastName,
                'importPath' => $relativePath,
            ];
        })->sortBy('eventName')->values();

        $imports = $events->map(
            fn ($event) => "import type { {$event['eventName']} } from '{$event['importPath']}';"
        )->values();

        /** @var view-string $template */
        $template = config()->string('ts-publish.broadcast_events.echo_augmentation.template');

        return view($template, [
            'echoPackage' => $echoPackage,
            'imports' => $imports->all(),
            'events' => $events->values()->all(),
        ])->render();
    }

    /**
     * Resolve the Echo npm package name for the declare module statement.
     *
     * Uses the configured echo_package value when set. If null, auto-detects
     * from the project's package.json. Defaults to '@laravel/echo'.
     *
     * Priority: config value → package.json detection → '@laravel/echo'
     */
    protected function resolveEchoPackage(): string
    {
        $configured = config('ts-publish.broadcast_events.echo_augmentation.echo_package');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->detectEchoPackageFromPackageJson() ?? '@laravel/echo';
    }

    /**
     * Detect @laravel/echo-vue or @laravel/echo-react from the project's package.json.
     *
     * Returns the first matching package name, or null if neither is found.
     */
    protected function detectEchoPackageFromPackageJson(): ?string
    {
        $packageJsonPath = base_path('package.json');

        if (! File::exists($packageJsonPath)) {
            return null;
        }

        /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>}|null $packageJson */
        $packageJson = json_decode(File::get($packageJsonPath), true);

        if (! is_array($packageJson)) {
            return null;
        }

        $allDeps = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        );

        foreach (['@laravel/echo-vue', '@laravel/echo-react', '@laravel/echo-svelte'] as $candidate) {
            if (isset($allDeps[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Write the rendered content to the configured output path.
     */
    protected function writeFile(string $content): void
    {
        $outputPath = $this->resolveOutputPath();
        $filename = config()->string('ts-publish.broadcast_events.echo_augmentation.filename', 'echo-broadcast-events.d.ts');

        $this->filesystem->ensureDirectoryExists($outputPath);
        $this->filesystem->put("{$outputPath}/{$filename}", $content);
    }

    /**
     * Resolve the output directory for the Echo augmentation file.
     *
     * Falls back: echo_augmentation.output_path → broadcast_events.output_path → output_directory.
     */
    protected function resolveOutputPath(): string
    {
        $echoOutputPath = config('ts-publish.broadcast_events.echo_augmentation.output_path');
        if (is_string($echoOutputPath)) {
            return $echoOutputPath;
        }

        $eventsOutputPath = config('ts-publish.broadcast_events.output_path');
        if (is_string($eventsOutputPath)) {
            return $eventsOutputPath;
        }

        return config()->string('ts-publish.output_directory');
    }
}
