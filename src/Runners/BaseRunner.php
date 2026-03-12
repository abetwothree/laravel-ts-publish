<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
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

    public protected(set) string $enumBarrelContent = '';

    public protected(set) string $modelBarrelContent = '';

    /** @var array<string, string> Barrel contents keyed by namespace path (modular mode only) */
    public protected(set) array $enumModularBarrels = [];

    /** @var array<string, string> Barrel contents keyed by namespace path (modular mode only) */
    public protected(set) array $modelModularBarrels = [];

    public protected(set) string $globalsContent = '';

    public protected(set) string $jsonContent = '';

    public protected(set) string $watcherJsonContent = '';

    abstract public function run(): void;
}
