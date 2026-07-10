<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\EnsuresDirectoryExists;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

class GlobalsWriter
{
    use EnsuresDirectoryExists;
    use WritesGeneratedFiles;

    public function __construct(
        protected Filesystem $filesystem,
    ) {}

    public function write(Runner $runner): string
    {
        if (! Config::boolean('ts-publish.globals.enabled')) {
            return '';
        }

        /** @var view-string $template */
        $template = Config::string('ts-publish.globals.template');

        // Build a map of global namespace → type names it owns, used for cross-namespace qualification.
        // Each key is a dot-separated namespace path, e.g. 'app.enums' => [...], 'app.models' => [...].
        /** @var array<string, list<string>> $globalTypesByNamespace */
        $globalTypesByNamespace = [];

        foreach ($runner->enumGenerators as $gen) {
            $t = $gen->transformer;
            $ns = str_replace('/', '.', $t->namespacePath);
            $globalTypesByNamespace[$ns][] = $t->enumName;
            $globalTypesByNamespace[$ns][] = $t->enumName.'Type';
            if ($t->backed) {
                $globalTypesByNamespace[$ns][] = $t->enumName.'Kind';
            }
        }

        foreach ($runner->modelGenerators as $gen) {
            $t = $gen->transformer;
            $ns = str_replace('/', '.', $t->namespacePath);
            $globalTypesByNamespace[$ns][] = $t->modelName;
        }

        foreach ($runner->resourceGenerators as $gen) {
            $t = $gen->transformer;
            $ns = str_replace('/', '.', $t->namespacePath);
            $globalTypesByNamespace[$ns][] = $t->resourceName;
        }

        foreach ($runner->formRequestGenerators as $gen) {
            $t = $gen->transformer;
            $ns = str_replace('/', '.', $t->namespacePath);
            $globalTypesByNamespace[$ns][] = $t->typeName;
        }

        foreach ($runner->broadcastEventGenerators as $gen) {
            $t = $gen->transformer;
            $ns = str_replace('/', '.', $t->namespacePath);
            $globalTypesByNamespace[$ns][] = $t->eventName;
        }

        // Collect external (non-relative) type imports needed at the top of the globals file.
        // Model customImports hold imports from #[TsExtends] and #[TsType] with custom paths.
        // Resource typeImports hold all resolved imports; filter to non-relative ones.
        /** @var array<string, list<string>> $externalTypeImports */
        $externalTypeImports = [];

        foreach ($runner->modelGenerators as $gen) {
            foreach ($gen->transformer->customImports as $path => $types) {
                foreach ($types as $type) {
                    if (! in_array($type, $externalTypeImports[$path] ?? [], true)) {
                        $externalTypeImports[$path][] = $type;
                    }
                }
            }
        }

        foreach ($runner->resourceGenerators as $gen) {
            foreach ($gen->transformer->typeImports as $path => $types) {
                if (str_starts_with($path, '.')) {
                    continue;
                }
                foreach ($types as $type) {
                    if (! in_array($type, $externalTypeImports[$path] ?? [], true)) {
                        $externalTypeImports[$path][] = $type;
                    }
                }
            }
        }

        foreach ($runner->broadcastEventGenerators as $gen) {
            foreach ($gen->transformer->typeImports as $path => $types) {
                if (str_starts_with($path, '.')) {
                    continue;
                }
                foreach ($types as $type) {
                    if (! in_array($type, $externalTypeImports[$path] ?? [], true)) {
                        $externalTypeImports[$path][] = $type;
                    }
                }
            }
        }

        $externalTypeImports = LaravelTsPublish::sortImportPaths($externalTypeImports);

        // Build a merged alias map from all transformers so the globals template can resolve
        // per-file import aliases (e.g. CrmUser, WorkbenchStatusType) to namespace-qualified names.
        /** @var array<string, string> $globalAliasMap */
        $globalAliasMap = [];

        foreach ($runner->modelGenerators as $gen) {
            $globalAliasMap = array_merge($globalAliasMap, $gen->transformer->globalAliasMap());
        }

        foreach ($runner->resourceGenerators as $gen) {
            $globalAliasMap = array_merge($globalAliasMap, $gen->transformer->globalAliasMap());
        }

        foreach ($runner->broadcastEventGenerators as $gen) {
            $globalAliasMap = array_merge($globalAliasMap, $gen->transformer->globalAliasMap());
        }

        $viewData = [
            'globalTypesByNamespace' => $globalTypesByNamespace,
            'globalAliasMap' => $globalAliasMap,
            'externalTypeImports' => $externalTypeImports,
        ];

        $viewData['groupedModels'] = $runner->modelGenerators
            ->groupBy(fn (ModelGenerator $g) => str_replace('/', '.', $g->transformer->namespacePath))
            ->map(fn ($group) => $group->map(fn (ModelGenerator $g) => $g->transformer))
            ->sortKeys();

        $viewData['groupedEnums'] = $runner->enumGenerators
            ->groupBy(fn (EnumGenerator $g) => str_replace('/', '.', $g->transformer->namespacePath))
            ->map(fn ($group) => $group->map(fn (EnumGenerator $g) => $g->transformer))
            ->sortKeys();

        $viewData['groupedResources'] = $runner->resourceGenerators
            ->groupBy(fn (ResourceGenerator $g) => str_replace('/', '.', $g->transformer->namespacePath))
            ->map(fn ($group) => $group->map(fn (ResourceGenerator $g) => $g->transformer))
            ->sortKeys();

        $viewData['groupedFormRequests'] = $runner->formRequestGenerators
            ->groupBy(fn (FormRequestGenerator $g) => str_replace('/', '.', $g->transformer->namespacePath))
            ->map(fn ($group) => $group->map(fn (FormRequestGenerator $g) => $g->transformer))
            ->sortKeys();

        $viewData['groupedBroadcastEvents'] = $runner->broadcastEventGenerators
            ->groupBy(fn (BroadcastEventGenerator $g) => str_replace('/', '.', $g->transformer->namespacePath))
            ->map(fn ($group) => $group->map(fn (BroadcastEventGenerator $g) => $g->transformer))
            ->sortKeys();

        $content = view($template, $viewData)->render();

        if (Config::boolean('ts-publish.output_to_files')) {
            $globalDir = Config::string('ts-publish.globals.output_directory');
            $outputPath = ! empty($globalDir) ? $globalDir : Config::string('ts-publish.output_directory');
            $filename = Config::string('ts-publish.globals.filename');

            $this->ensureDirectoryExists($outputPath);
            $this->putIfChanged("$outputPath/$filename", $content);
        }

        return $content;
    }
}
