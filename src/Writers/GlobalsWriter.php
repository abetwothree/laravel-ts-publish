<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Writers;

use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
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
            $ns = $t->globalNamespace();
            $globalTypesByNamespace[$ns][] = $t->enumName;
            $globalTypesByNamespace[$ns][] = $t->enumName.'Type';
            if ($t->backed) {
                $globalTypesByNamespace[$ns][] = $t->enumName.'Kind';
            }
        }

        foreach ($runner->modelGenerators as $gen) {
            $t = $gen->transformer;
            $ns = $t->globalNamespace();
            $globalTypesByNamespace[$ns][] = $t->modelName;
        }

        foreach ($runner->resourceGenerators as $gen) {
            $t = $gen->transformer;
            $ns = $t->globalNamespace();
            $globalTypesByNamespace[$ns][] = $t->resourceName;
        }

        foreach ($runner->formRequestGenerators as $gen) {
            $t = $gen->transformer;
            $ns = $t->globalNamespace();
            $globalTypesByNamespace[$ns][] = $t->typeName;
        }

        foreach ($runner->broadcastEventGenerators as $gen) {
            $t = $gen->transformer;
            $ns = $t->globalNamespace();
            $globalTypesByNamespace[$ns][] = $t->eventName;
        }

        // Collect external (non-relative) type imports needed at the top of the globals file.
        // A model's combined custom imports are the #[TsExtends], #[TsCasts] and #[TsType] ones its interface uses.
        // Resource, broadcast-event and form-request typeImports hold all resolved imports; non-relative only.
        /** @var array<string, list<string>> $externalTypeImports */
        $externalTypeImports = [];

        foreach ($runner->modelGenerators as $gen) {
            foreach ($gen->transformer->combinedCustomImports() as $path => $types) {
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

        foreach ($runner->formRequestGenerators as $gen) {
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

        // Sorted by name so the import lines do not depend on the order the classes were collected in.
        foreach ($externalTypeImports as $path => $types) {
            sort($types);
            $externalTypeImports[$path] = $types;
        }

        $externalTypeImports = TsNaming::sortImportPaths($externalTypeImports);

        // The package's template qualifies through each transformer's own globalTypeReferenceMap(). This merged map
        // stays in the view data because a template a project published before that may still read it.
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
            ->groupBy(fn (ModelGenerator $g) => $g->transformer->globalNamespace())
            ->map(fn ($group) => $group->map(fn (ModelGenerator $g) => $g->transformer))
            ->sortKeys();

        $viewData['groupedEnums'] = $runner->enumGenerators
            ->groupBy(fn (EnumGenerator $g) => $g->transformer->globalNamespace())
            ->map(fn ($group) => $group->map(fn (EnumGenerator $g) => $g->transformer))
            ->sortKeys();

        $viewData['groupedResources'] = $runner->resourceGenerators
            ->groupBy(fn (ResourceGenerator $g) => $g->transformer->globalNamespace())
            ->map(fn ($group) => $group->map(fn (ResourceGenerator $g) => $g->transformer))
            ->sortKeys();

        $viewData['groupedFormRequests'] = $runner->formRequestGenerators
            ->groupBy(fn (FormRequestGenerator $g) => $g->transformer->globalNamespace())
            ->map(fn ($group) => $group->map(fn (FormRequestGenerator $g) => $g->transformer))
            ->sortKeys();

        $viewData['groupedBroadcastEvents'] = $runner->broadcastEventGenerators
            ->groupBy(fn (BroadcastEventGenerator $g) => $g->transformer->globalNamespace())
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
