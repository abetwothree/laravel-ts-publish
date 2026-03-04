<?php

namespace AbeTwoThree\LaravelTsPublish\Tests;

use AbeTwoThree\LaravelTsPublish\LaravelTsPublishServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Orchestra\Testbench\Attributes\WithEnv;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\workbench_path;

#[WithEnv('DB_CONNECTION', 'testing')]
class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'AbeTwoThree\\LaravelTsPublish\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelTsPublishServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // copy config file from package to workbench config to keep it in sync
        $packageConfigPath = __DIR__.'/../config/ts-publish.php';
        $workbenchConfigPath = workbench_path('config/ts-publish.php');
        copy($packageConfigPath, $workbenchConfigPath);

        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:yTtQNlEOB1IqYydLG9Z5pKRSxhZffdOxT1iuZIJi+eM=');
        config()->set('ts-publish.output_globals_file', true);
        config()->set('ts-publish.output_json_file', true);
        config()->set('ts-publish.additional_model_directories', [
            DatabaseNotification::class,
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(
            workbench_path('database/migrations')
        );
    }
}
