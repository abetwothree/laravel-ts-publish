<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\CacheBootstrap;
use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\BaseRunner;
use Illuminate\Support\Facades\Config;
use Workbench\App\Models\User;

/**
 * A custom generator that does NOT use RehydratesFromCache (no ::fromCache()).
 *
 * @extends CoreGenerator<object>
 */
class GuardFixtureGenerator extends CoreGenerator
{
    public object $transformer;

    public function generate(): string
    {
        $this->transformer = (object) ['ok' => true];

        return $this->content = 'fixture-output';
    }

    public function filename(): string
    {
        return 'fixture';
    }
}

beforeEach(function () {
    Config::set('ts-publish.cache.store', null);
    Config::set('ts-publish.cache.directory', sys_get_temp_dir().'/ts-publish-guard-'.uniqid());

    $this->runner = new class extends BaseRunner
    {
        public function run(): void {}

        public function build(string $fqcn, string $generatorClass): CoreGenerator
        {
            return $this->cachedGenerate($fqcn, $generatorClass);
        }
    };
});

it('does not crash and does not cache a generator without fromCache()', function () {
    $manifest = CacheBootstrap::manifest();
    $this->runner->useCache($manifest);

    // Two builds: with a missing fromCache(), the second must NOT take a hit path.
    $first = $this->runner->build(User::class, GuardFixtureGenerator::class);
    $second = $this->runner->build(User::class, GuardFixtureGenerator::class);

    expect($first)->toBeInstanceOf(GuardFixtureGenerator::class)
        ->and($second)->toBeInstanceOf(GuardFixtureGenerator::class)
        ->and($manifest->snapshot(User::class))->toBeNull();
});
