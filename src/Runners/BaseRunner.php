<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use Illuminate\Support\Collection;

abstract class BaseRunner
{
    protected BarrelWriter $barrelWriter;

    protected GlobalsWriter $globalsWriter;

    /** @var Collection<int, EnumGenerator> */
    public protected(set) Collection $enumGenerators;

    /** @var Collection<int, ModelGenerator> */
    public protected(set) Collection $modelGenerators;

    /** @var Collection<int, ResourceGenerator> */
    public protected(set) Collection $resourceGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $enumModularBarrels = [];

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $modelModularBarrels = [];

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $resourceModularBarrels = [];

    public protected(set) string $globalsContent = '';

    public protected(set) string $jsonContent = '';

    public protected(set) string $watcherJsonContent = '';

    public protected(set) string $viteEnvContent = '';

    /** @var Collection<int, RouteGenerator> */
    public protected(set) Collection $routeGenerators;

    /** @var array<string, string> Barrel contents keyed by namespace path */
    public protected(set) array $routeModularBarrels = [];

    public bool $shouldPublishEnums = true;

    public bool $shouldPublishModels = true;

    public bool $shouldPublishResources = true;

    public bool $shouldPublishRoutes = true;

    abstract public function run(): void;
}
