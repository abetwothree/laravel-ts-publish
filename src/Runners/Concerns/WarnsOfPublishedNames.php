<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners\Concerns;

use AbeTwoThree\LaravelTsPublish\Generators\ClassTypeGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Support\NamespaceNameCollisions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * The run's warnings of published names that one barrel, the globals file or one importing file cannot hold together.
 *
 * @phpstan-import-type Publisher from NamespaceNameCollisions
 *
 * @phpstan-require-extends Runner
 */
trait WarnsOfPublishedNames
{
    /**
     * Warn of each name two published or retained classes publish in one namespace, which its barrel and the globals
     * file cannot export twice. Enums claim their names first, so a class that collides with one is the one warned of.
     */
    protected function warnOfCollidingPublishedNames(): void
    {
        $generators = [...$this->publishedGenerators(), $this->classTypeGenerators, $this->retainedClassTypeGenerators];

        /** @var list<Publisher> $publishers */
        $publishers = [];

        foreach ($generators as $collection) {
            foreach ($collection as $generator) {
                // A route barrel re-exports each file's default alone, so a route file's names reach no shared barrel.
                if ($generator instanceof RouteGenerator) {
                    continue;
                }

                $publishers[] = [
                    'namespace' => $generator->namespacePath(),
                    'fqcn' => $generator->transformer->fqcn(),
                    'names' => $generator->transformer->declaredNames(),
                ];
            }
        }

        foreach (NamespaceNameCollisions::find($publishers) as $collision) {
            AnalysisWarnings::addOnce($collision['subject'], sprintf(
                'Publishes the name [%s], which [%s] also publishes in the same namespace, so their barrel and the globals file cannot export both. Give one of them another name, or move it to a namespace of its own.',
                $collision['name'],
                $collision['other'],
            ));
        }
    }

    /**
     * Warn of each module written where route controllers publish: their barrel re-exports defaults, and this phase's
     * write keeps only `export *` lines, so the directory's barrel drops the controllers' exports.
     *
     * @param  Collection<int, ClassTypeGenerator>  $generators
     */
    protected function warnOfSharedRouteBarrels(Collection $generators): void
    {
        $routesBase = Config::get('ts-publish.routes.output_directory');
        $routesBase = is_string($routesBase) && $routesBase !== '' ? $routesBase : Config::string('ts-publish.output_directory');

        if (rtrim($routesBase, '/') !== rtrim(Config::string('ts-publish.output_directory'), '/')) {
            return;
        }

        foreach ($generators as $generator) {
            if (isset($this->routeModularBarrels[$generator->namespacePath()])) {
                AnalysisWarnings::addOnce($generator->findable, sprintf(
                    'Publishes its composed type in [%s], where route controllers publish too, so that directory\'s barrel drops their default exports. Move the class to a namespace of its own.',
                    $generator->namespacePath(),
                ));
            }
        }
    }

    /**
     * Warn of each published or retained file that reaches two composed classes of one type name, which its file and
     * its globals entry cannot tell apart. A cache hit runs no transform, so this reads what each file stored.
     */
    protected function warnOfSharedComposedNames(): void
    {
        foreach ($this->publishedGenerators() as $generators) {
            foreach ($generators as $generator) {
                ComposedClassTypes::warnOfSharedNames($generator->transformer->fqcn(), $generator->transformer->composedClassTypes());
            }
        }
    }
}
