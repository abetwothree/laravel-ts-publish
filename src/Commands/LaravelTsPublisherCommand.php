<?php

namespace AbeTwoThree\LaravelTsPublisher\Commands;

use Illuminate\Console\Command;

class LaravelTsPublisherCommand extends Command
{
    public $signature = 'laravel-ts-publisher';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
