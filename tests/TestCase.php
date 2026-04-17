<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests;

use AbeTwoThree\LaravelTsPublish\LaravelTsPublishServiceProvider;
use AbeTwoThree\LaravelTsPublish\RelationMap;
use AbeTwoThree\LaravelTsPublish\TypeScriptMap;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Orchestra\Testbench\Attributes\WithEnv;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\workbench_path;

use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Workbench\Accounting\Enums\InvoiceStatus;
use Workbench\Accounting\Enums\PaymentStatus;
use Workbench\Accounting\Models\Invoice;
use Workbench\Shipping\Enums\Status;
use Workbench\Shipping\Models\Shipment;

#[WithEnv('DB_CONNECTION', 'testing')]
class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithWorkbench;

    private static bool $configSynced = false;

    /** @var list<string>|null */
    private static ?array $cachedModules = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset the TypeScriptMap static cache so custom_ts_mappings from
        // one test don't leak into subsequent tests across files.
        $prop = (new ReflectionClass(TypeScriptMap::class))->getProperty('map');
        $prop->setValue(null, null);

        // Reset the RelationMap static cache for the same reason.
        $prop = (new ReflectionClass(RelationMap::class))->getProperty('map');
        $prop->setValue(null, null);

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
        if (! self::$configSynced) {
            // Sync package config to workbench once per process (atomic to avoid parallel race conditions)
            $packageConfigPath = __DIR__.'/../config/ts-publish.php';
            $workbenchConfigPath = workbench_path('config/ts-publish.php');
            $tmpPath = $workbenchConfigPath.'.'.getmypid().'.tmp';
            copy($packageConfigPath, $tmpPath);
            rename($tmpPath, $workbenchConfigPath);

            self::$cachedModules = collect(
                iterator_to_array(
                    (new Finder)->in(workbench_path('modules'))->directories()->depth('< 5')
                )
            )
                ->map(fn (SplFileInfo $file) => $file->getPathname())
                ->all();

            self::$configSynced = true;
        }

        $modules = self::$cachedModules;

        config()->set([
            'database.default' => 'testing',
            'ts-publish.output_directory' => workbench_path('resources/js/types/'),
            'ts-publish.output_globals_file' => true,
            'ts-publish.output_json_file' => true,
            'ts-publish.output_collected_files_json' => true,
            'ts-publish.models.additional_directories' => [
                DatabaseNotification::class,
                Invoice::class,
                Shipment::class,
                "Workbench\Shipping\Models\FalseShipmentClass",
                ...$modules,
            ],
            'ts-publish.enums.additional_directories' => [
                InvoiceStatus::class,
                PaymentStatus::class,
                Status::class,
                "Workbench\Shipping\Enums\FalseStatusClass",
                ...$modules,
            ],
            'ts-publish.resources.additional_directories' => $modules,
        ]);
    }
}
