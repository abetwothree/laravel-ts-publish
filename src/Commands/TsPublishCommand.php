<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Commands;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Runners\RunnerForSource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class TsPublishCommand extends Command
{
    protected $signature = 'ts:publish
        {--preview=false : Output generated TypeScript declarations to the console instead of writing to files}
        {--source= : FQCN or file path of a specific enum or model to republish}
        {--only-enums : Only publish enums (ignoring models and resources)}
        {--only-models : Only publish models (ignoring enums and resources)}
        {--only-resources : Only publish resources (ignoring enums and models)}';

    protected $description = 'Publish All TypeScript files from enums, models & resources';

    public function handle(): int
    {
        LaravelTsPublish::callCommandWith();

        /** @var string|null $source */
        $source = $this->option('source');

        $validateOnlyOptions = $this->validateOnlyOptions();

        if ($validateOnlyOptions !== self::SUCCESS) {
            return $validateOnlyOptions;
        }

        return $source ? $this->runSource($source) : $this->runAll();
    }

    protected function validateOnlyOptions(): int
    {
        $onlyEnums = (bool) $this->option('only-enums');
        $onlyModels = (bool) $this->option('only-models');
        $onlyResources = (bool) $this->option('only-resources');

        $onlyCount = (int) $onlyEnums + (int) $onlyModels + (int) $onlyResources;

        if ($onlyCount > 1) {
            if (! $this->output->isQuiet()) {
                error('The --only-enums, --only-models, and --only-resources options cannot be used together. Please specify only one or none of these options.');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function runAll(): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        config()->set('ts-publish.output_to_files', ! $preview);

        if (! $this->output->isQuiet()) {
            intro('ts:publish');
        }

        $runner = resolve(Runner::class);
        $flags = $this->resolvePublishFlags();

        if ($flags === null) {
            return self::SUCCESS;
        }

        [$runner->shouldPublishEnums, $runner->shouldPublishModels, $runner->shouldPublishResources] = $flags;
        $runner->run();

        if (! $this->output->isQuiet()) {
            if ($preview) {
                $this->createPreview($runner);
            } else {
                $this->createPublishedFilesList($runner);
            }

            if ($this->output->isVerbose()) {
                $enumCount = count($runner->enumGenerators);
                $modelCount = count($runner->modelGenerators);
                $resourceCount = count($runner->resourceGenerators);

                outro("{$enumCount} enums, {$modelCount} models, {$resourceCount} resources — All done");
            } else {
                outro('All done');
            }
        }

        return self::SUCCESS;
    }

    protected function runSource(string $source): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        config()->set('ts-publish.output_to_files', ! $preview);

        if (! $this->output->isQuiet()) {
            intro('ts:publish --source');
        }

        try {
            $runner = new RunnerForSource($source);
            $flags = $this->resolvePublishFlags();

            if ($flags === null) {
                return self::SUCCESS;
            }

            [$runner->shouldPublishEnums, $runner->shouldPublishModels, $runner->shouldPublishResources] = $flags;
            $runner->run();
        } catch (InvalidArgumentException $e) {
            if (! $this->output->isQuiet()) {
                error($e->getMessage());
            }

            return self::FAILURE;
        }

        if (! $this->output->isQuiet()) {
            if ($preview) {
                $this->createPreview($runner);
            } else {
                $this->createPublishedFilesList($runner);
            }

            $enumCount = count($runner->enumGenerators);
            $modelCount = count($runner->modelGenerators);
            $resourceCount = count($runner->resourceGenerators);

            outro("{$enumCount} enums, {$modelCount} models, {$resourceCount} resources — All done");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the final publish flags from config values and command options.
     *
     * @return array{0: bool, 1: bool, 2: bool}|null [shouldPublishEnums, shouldPublishModels, shouldPublishResources] or null to abort
     */
    protected function resolvePublishFlags(): ?array
    {
        $configEnums = config()->boolean('ts-publish.publish_enums');
        $configModels = config()->boolean('ts-publish.publish_models');
        $configResources = config()->boolean('ts-publish.publish_resources');
        $onlyEnums = (bool) $this->option('only-enums');
        $onlyModels = (bool) $this->option('only-models');
        $onlyResources = (bool) $this->option('only-resources');

        $shouldPublishEnums = $configEnums;
        $shouldPublishModels = $configModels;
        $shouldPublishResources = $configResources;

        if ($onlyEnums) {
            $shouldPublishModels = false;
            $shouldPublishResources = false;

            if (! $configEnums) {
                $shouldPublishEnums = $this->promptConfigOverride('enums');

                if (! $shouldPublishEnums) {
                    return null;
                }
            }
        }

        if ($onlyModels) {
            $shouldPublishEnums = false;
            $shouldPublishResources = false;

            if (! $configModels) {
                $shouldPublishModels = $this->promptConfigOverride('models');

                if (! $shouldPublishModels) {
                    return null;
                }
            }
        }

        if ($onlyResources) {
            $shouldPublishEnums = false;
            $shouldPublishModels = false;

            if (! $configResources) {
                $shouldPublishResources = $this->promptConfigOverride('resources');

                if (! $shouldPublishResources) {
                    return null;
                }
            }
        }

        if (! $shouldPublishEnums && ! $shouldPublishModels && ! $shouldPublishResources) {
            if (! $this->output->isQuiet()) {
                warning('Enums, models, and resources are all disabled in config. Nothing to publish.');
            }

            return null;
        }

        return [$shouldPublishEnums, $shouldPublishModels, $shouldPublishResources];
    }

    protected function promptConfigOverride(string $type): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm("Config has {$type} publishing disabled. Override and publish {$type} anyway?", default: false);
    }

    protected function createPreview(Runner|RunnerForSource $runner): void
    {
        info('Previewing generated TypeScript declarations');

        if (count($runner->enumGenerators) > 0) {
            $this->newLine();
            $this->comment('Enums:');
            foreach ($runner->enumGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        if (! empty($runner->enumBarrelContent)) {
            $this->newLine();
            if (count($runner->enumModularBarrels) > 0) {
                $this->comment('Enum Barrel Files:');
                foreach ($runner->enumModularBarrels as $namespacePath => $content) {
                    $this->newLine();
                    $this->comment("  {$namespacePath}/index.ts");
                    $this->line($content);
                }
            } else {
                $this->comment('Enum Barrel File:');
                $this->newLine();
                $this->comment('  index.ts');
                $this->line($runner->enumBarrelContent);
            }
        }

        if (count($runner->modelGenerators) > 0) {
            $this->newLine();
            $this->comment('Models:');
            foreach ($runner->modelGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        if (! empty($runner->modelBarrelContent)) {
            $this->newLine();
            if (count($runner->modelModularBarrels) > 0) {
                $this->comment('Model Barrel Files:');
                foreach ($runner->modelModularBarrels as $namespacePath => $content) {
                    $this->newLine();
                    $this->comment("  {$namespacePath}/index.ts");
                    $this->line($content);
                }
            } else {
                $this->comment('Model Barrel File:');
                $this->newLine();
                $this->comment('  index.ts');
                $this->line($runner->modelBarrelContent);
            }
        }

        if (count($runner->resourceGenerators) > 0) {
            $this->newLine();
            $this->comment('Resources:');
            foreach ($runner->resourceGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        if (! empty($runner->resourceBarrelContent)) {
            $this->newLine();
            if (count($runner->resourceModularBarrels) > 0) {
                $this->comment('Resource Barrel Files:');
                foreach ($runner->resourceModularBarrels as $namespacePath => $content) {
                    $this->newLine();
                    $this->comment("  {$namespacePath}/index.ts");
                    $this->line($content);
                }
            } else {
                $this->comment('Resource Barrel File:');
                $this->newLine();
                $this->comment('  index.ts');
                $this->line($runner->resourceBarrelContent);
            }
        }
    }

    protected function createPublishedFilesList(Runner|RunnerForSource $runner): void
    {
        $outputDirectory = config()->string('ts-publish.output_directory');

        info("Published to: {$outputDirectory}");

        if ($this->output->isVerbose()) {
            $this->createVerboseFilesList($runner);
        } else {
            $this->createCompactSummary($runner);
        }
    }

    protected function createCompactSummary(Runner|RunnerForSource $runner): void
    {
        $enumCount = $runner->enumGenerators->count();
        $modelCount = $runner->modelGenerators->count();
        $resourceCount = $runner->resourceGenerators->count();

        $parts = [];

        if ($enumCount > 0) {
            $parts[] = Str::plural('enum', $enumCount, true);
        }

        if ($modelCount > 0) {
            $parts[] = Str::plural('model', $modelCount, true);
        }

        if ($resourceCount > 0) {
            $parts[] = Str::plural('resource', $resourceCount, true);
        }

        if (count($parts) > 0) {
            $this->line('  '.implode(', ', $parts));
        }

        $extras = $this->collectExtras($runner);

        if (count($extras) > 0) {
            $grouped = collect($extras)->groupBy(fn (array $e) => $e[0]);
            $summary = $grouped->map(fn ($items, string $type) => $items->count() === 1
                ? Str::lower($type)
                : $items->count().' '.Str::lower(Str::plural($type, $items->count())),
            )->values()->implode(', ');

            $this->line("  Extras: {$summary}");
        }
    }

    protected function createVerboseFilesList(Runner|RunnerForSource $runner): void
    {
        if (count($runner->enumGenerators) > 0) {
            /** @var array<int, array<int, string>> $enumRows */
            $enumRows = $runner->enumGenerators->map(fn (EnumGenerator $g) => [
                $g->transformer->enumName,
                $g->filename().'.ts',
                (string) count($g->transformer->cases),
                (string) count($g->transformer->methods),
                (string) count($g->transformer->staticMethods),
            ])->toArray();

            table(
                headers: ['Enum', 'File', 'Cases', 'Methods', 'Static Methods'],
                rows: $enumRows,
            );
        }

        if (count($runner->modelGenerators) > 0) {
            /** @var array<int, array<int, string>> $modelRows */
            $modelRows = $runner->modelGenerators->map(fn (ModelGenerator $g) => [
                $g->transformer->modelName,
                $g->filename().'.ts',
                (string) count($g->transformer->columns),
                (string) count($g->transformer->mutators),
                (string) count($g->transformer->relations),
            ])->toArray();

            table(
                headers: ['Model', 'File', 'Columns', 'Mutators', 'Relations'],
                rows: $modelRows,
            );
        }

        if (count($runner->resourceGenerators) > 0) {
            /** @var array<int, array<int, string>> $resourceRows */
            $resourceRows = $runner->resourceGenerators->map(fn (ResourceGenerator $g) => [
                $g->transformer->resourceName,
                $g->filename().'.ts',
                (string) count($g->transformer->properties),
            ])->toArray();

            table(
                headers: ['Resource', 'File', 'Properties'],
                rows: $resourceRows,
            );
        }

        $extras = $this->collectExtras($runner);

        if (count($extras) > 0) {
            /** @var array<int, array<int, string>> $extras */
            table(
                headers: ['Type', 'File'],
                rows: $extras,
            );
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function collectExtras(Runner|RunnerForSource $runner): array
    {
        return array_filter([
            ...($runner->enumModularBarrels
                ? array_map(fn (string $path) => ['Barrel', "{$path}/index.ts"], array_keys($runner->enumModularBarrels))
                : ($runner->enumBarrelContent ? [['Barrel', 'enums/index.ts']] : [])),
            ...($runner->modelModularBarrels
                ? array_map(fn (string $path) => ['Barrel', "{$path}/index.ts"], array_keys($runner->modelModularBarrels))
                : ($runner->modelBarrelContent ? [['Barrel', 'models/index.ts']] : [])),
            ...($runner->resourceModularBarrels
                ? array_map(fn (string $path) => ['Barrel', "{$path}/index.ts"], array_keys($runner->resourceModularBarrels))
                : ($runner->resourceBarrelContent ? [['Barrel', config()->string('ts-publish.resources_namespace', 'resources').'/index.ts']] : [])),
            $runner->globalsContent ? ['Globals', config()->string('ts-publish.global_filename')] : null,
            $runner->jsonContent ? ['JSON', config()->string('ts-publish.json_filename')] : null,
        ]);
    }
}
