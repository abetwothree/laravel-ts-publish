<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Commands;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Runners\RunnerForSource;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;

class TsPublishCommand extends Command
{
    protected $signature = 'ts:publish
        {--preview=false : Output generated TypeScript declarations to the console instead of writing to files}
        {--source= : FQCN or file path of a specific enum or model to republish}';

    protected $description = 'Publish All TypeScript files from enums & models';

    public function handle(): int
    {
        LaravelTsPublish::callCommandWith();

        /** @var string|null $source */
        $source = $this->option('source');

        return $source ? $this->runSource($source) : $this->runAll();
    }

    protected function runAll(): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        config()->set('ts-publish.output_to_files', ! $preview);

        intro('ts:publish');

        $runner = resolve(Runner::class);
        $runner->run();

        if ($preview) {
            $this->createPreview($runner);
        } else {
            $this->createPublishedFilesList($runner);
        }

        $enumCount = count($runner->enumGenerators);
        $modelCount = count($runner->modelGenerators);

        outro("{$enumCount} enums, {$modelCount} models — All done");

        return self::SUCCESS;
    }

    protected function runSource(string $source): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        config()->set('ts-publish.output_to_files', ! $preview);

        intro('ts:publish --source');

        try {
            $runner = new RunnerForSource($source);
            $runner->run();
        } catch (InvalidArgumentException $e) {
            error($e->getMessage());

            return self::FAILURE;
        }

        if ($preview) {
            $this->createPreview($runner);
        } else {
            $this->createPublishedFilesList($runner);
        }

        $enumCount = count($runner->enumGenerators);
        $modelCount = count($runner->modelGenerators);

        outro("{$enumCount} enums, {$modelCount} models — All done");

        return self::SUCCESS;
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
    }

    protected function createPublishedFilesList(Runner|RunnerForSource $runner): void
    {
        $outputDirectory = config()->string('ts-publish.output_directory');

        info("Published to: {$outputDirectory}");

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

        $extras = array_filter([
            ...($runner->enumModularBarrels
                ? array_map(fn (string $path) => ['Barrel', "{$path}/index.ts"], array_keys($runner->enumModularBarrels))
                : ($runner->enumBarrelContent ? [['Barrel', 'enums/index.ts']] : [])),
            ...($runner->modelModularBarrels
                ? array_map(fn (string $path) => ['Barrel', "{$path}/index.ts"], array_keys($runner->modelModularBarrels))
                : ($runner->modelBarrelContent ? [['Barrel', 'models/index.ts']] : [])),
            $runner->globalsContent ? ['Globals', config()->string('ts-publish.global_filename')] : null,
            $runner->jsonContent ? ['JSON', config()->string('ts-publish.json_filename')] : null,
        ]);

        if (count($extras) > 0) {
            /** @var array<int, array<int, string>> $extras */
            table(
                headers: ['Type', 'File'],
                rows: $extras,
            );
        }
    }
}
