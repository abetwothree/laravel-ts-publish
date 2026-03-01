<?php

namespace AbeTwoThree\LaravelTsPublish\Commands;

use AbeTwoThree\LaravelTsPublish\Runner;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'ts:publish',
    description: 'Publish All TypeScript declaration files'
)]
class TsPublishCommand extends Command
{
    public function handle(): int
    {
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

        $this->comment('All done');

        return self::SUCCESS;
    }
}
