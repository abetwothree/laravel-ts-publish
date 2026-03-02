<?php

namespace AbeTwoThree\LaravelTsPublish\Commands;

use AbeTwoThree\LaravelTsPublish\Runner;
use Illuminate\Console\Command;

class TsPublishCommand extends Command
{
    protected $signature = 'ts:publish
        {--preview=false : Output generated TypeScript declarations to the console instead of writing to files}
        ';

    protected $description = 'Publish All TypeScript files from enums & models';

    public function handle(): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        config()->set('ts-publish.output_to_files', ! $preview);

        /**
         * Steps to publish TypeScript declaration files:
         * 1. Pass CLI options to a DTO to hold all options to pass around the app
         * 2. Find all Enums in the app and pass each to an enum generator class - create collection of generator enums
         * 3. Find all Models in the app and pass each to a model generator class - create collection of generator models
         * 4. Find all Resources in the app and pass each to a resource generator class - create collection of generator resources
         * 5. Pass collections of generator enums, models, and resources to a file generator class to create TypeScript declaration files in the specified output directory
         * 6. Handle any errors that may occur during the process and output appropriate messages to the console
         */
        $runner = resolve(Runner::class);
        $runner->run();

        if ($preview) {
            $this->createPreview($runner);
        } else {
            $this->createPublishedFilesList($runner);
        }

        $this->line('');
        $this->comment('All done');

        return self::SUCCESS;
    }

    protected function createPreview(Runner $runner): void
    {
        $this->line('');
        $this->info('Previewing generated TypeScript declarations:');

        // Output the generated TypeScript declarations to the console, grouped by type (enums & models)
        // Title display: "Enums"
        // For each enum generator, output the filename and content
        // Title display: "Models"
        // For each model generator, output the filename and content
        //
        // use the runner's public properties $modelGenerators, $enumGenerators to get the file names
        // The full path is the file name plus the output directory from the config
        //

        if (count($runner->enumGenerators) > 0) {
            $this->line('');
            $this->comment("\nEnums:");
            foreach ($runner->enumGenerators as $generator) {
                $this->line('');
                $this->info("File: {$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        // display the runner enumBarrelContent as well, with the filename "index.ts" and content from the runner property $enumBarrelContent
        if (! empty($runner->enumBarrelContent)) {
            $this->line('');
            $this->comment("\nEnum Barrel File:");
            $this->line('');
            $this->info('File: index.ts');
            $this->line($runner->enumBarrelContent);
        }

        if (count($runner->modelGenerators) > 0) {
            $this->line('');
            $this->comment("\nModels:");
            foreach ($runner->modelGenerators as $generator) {
                $this->line('');
                $this->info("File: {$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        // display the runner modelBarrelContent as well, with the filename "index.ts" and content from the runner property $modelBarrelContent
        if (! empty($runner->modelBarrelContent)) {
            $this->line('');
            $this->comment("\nModel Barrel File:");
            $this->line('');
            $this->info('File: index.ts');
            $this->line($runner->modelBarrelContent);
        }
    }

    protected function createPublishedFilesList(Runner $runner): void
    {
        $this->line('');
        $this->info('Published the following TypeScript declaration files:');
        // create a list like this:
        // Published files to this directory: ts-publish.output_directory
        // | Enums: | Path |
        // | - EnumName1.ts | full file path |
        // | - EnumName2.ts | full file path |
        //
        // | Models: | Path |
        // | - ModelName1.ts | full file path |
        // | - ModelName2.ts | full file path |
        //
        // use the runner's public properties $modelGenerators, $enumGenerators to get the file names
        // The full path is the file name plus the output directory from the config
        //

        $outputDirectory = config('ts-publish.output_directory');

        $this->line('');
        $this->info("Published files to this directory: {$outputDirectory}");

        if (count($runner->enumGenerators) > 0) {
            $this->line('');
            $this->comment("\nEnums:");
            $this->table(
                ['Enum File Name', 'File Path'],
                collect($runner->enumGenerators)
                    ->map(fn ($generator) => [
                        $generator->filename().'.ts',
                        $outputDirectory.$generator->filename().'.ts',
                    ])->toArray()
            );
        }

        if (count($runner->modelGenerators) > 0) {
            $this->line('');
            $this->comment("\nModels:");
            $this->table(
                ['Model File Name', 'File Path'],
                collect($runner->modelGenerators)
                    ->map(fn ($generator) => [
                        $generator->filename().'.ts',
                        $outputDirectory.$generator->filename().'.ts',
                    ])->toArray()
            );
        }

        $this->line('');
        $this->info('TypeScript declaration files published successfully!');
    }
}
