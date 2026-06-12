<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Commands;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Runners\RunnerForSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class TsPublishCommand extends Command
{
    protected $signature = 'ts:publish
        {--preview=false : Output generated TypeScript declarations to the console instead of writing to files}
        {--source= : FQCN or file path of a specific supported class to republish}
        {--only-broadcast-channels : Only publish broadcast channel types (ignoring all other types)}
        {--only-broadcast-events : Only publish broadcast event types (ignoring all other types)}
        {--only-form-requests : Only publish form requests (ignoring enums, models, resources, and routes)}
        {--only-functional : Only publish enabled functional content like routes & enums}
        {--only-enums : Only publish enums (ignoring models, resources, and routes)}
        {--only-models : Only publish models (ignoring enums, resources, and routes)}
        {--only-resources : Only publish resources (ignoring enums, models, and routes)}
        {--only-routes : Only publish routes (ignoring enums, models, and resources)}';

    protected $description = 'Publish TypeScript files from enums, models, resources, routes, form requests, broadcast channels, and broadcast events';

    public function handle(): int
    {
        LaravelTsPublish::callCommandWith();

        /** @var string|null $source */
        $source = $this->option('source');

        $validateOnlyOptions = $this->validateOnlyOptions();

        if ($validateOnlyOptions !== self::SUCCESS) {
            return $validateOnlyOptions;
        }

        return $source ? $this->runSource($source) : $this->runAll();
    }

    protected function validateOnlyOptions(): int
    {
        $onlyFunctional = (bool) $this->option('only-functional');

        if ($onlyFunctional) {
            if (! $this->output->isQuiet()) {
                info('The --only-functional flag is set. This will publish only functional content like enums & routes. All other --only-* flags will be ignored.');
            }

            return self::SUCCESS;
        }

        $onlyEnums = (bool) $this->option('only-enums');
        $onlyModels = (bool) $this->option('only-models');
        $onlyResources = (bool) $this->option('only-resources');
        $onlyRoutes = (bool) $this->option('only-routes');
        $onlyFormRequests = (bool) $this->option('only-form-requests');
        $onlyBroadcastChannels = (bool) $this->option('only-broadcast-channels');
        $onlyBroadcastEvents = (bool) $this->option('only-broadcast-events');

        $onlyCount = (int) $onlyEnums + (int) $onlyModels + (int) $onlyResources + (int) $onlyRoutes + (int) $onlyFormRequests + (int) $onlyBroadcastChannels + (int) $onlyBroadcastEvents;

        if ($onlyCount > 1) {
            if (! $this->output->isQuiet()) {
                error('The --only-enums, --only-models, --only-resources, --only-routes, --only-form-requests, --only-broadcast-channels, and --only-broadcast-events options cannot be used together. Please specify only one or none of these options.');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function runAll(): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        Config::set('ts-publish.output_to_files', ! $preview);

        if (! $this->output->isQuiet()) {
            intro('ts:publish');
        }

        $runner = resolve(Runner::class);
        $flags = $this->resolvePublishFlags();

        if ($flags === null) {
            return self::SUCCESS;
        }

        [
            $runner->shouldPublishEnums,
            $runner->shouldPublishModels,
            $runner->shouldPublishResources,
            $runner->shouldPublishRoutes,
            $runner->shouldPublishFormRequests,
            $runner->shouldPublishBroadcastChannels,
            $runner->shouldPublishBroadcastEvents,
        ] = $flags;

        try {
            $runner->run();
        } catch (InvalidArgumentException $e) {
            if (! $this->output->isQuiet()) {
                error($e->getMessage());
            }

            return self::FAILURE;
        }

        if (! $this->output->isQuiet()) {
            if ($preview) {
                $this->createPreview($runner);
            } else {
                $this->createPublishedFilesList($runner);
            }

            if ($this->output->isVerbose()) {
                $enumCount = count($runner->enumGenerators);
                $modelCount = count($runner->modelGenerators);
                $resourceCount = count($runner->resourceGenerators);
                $routeCount = count($runner->routeGenerators);
                $formRequestCount = count($runner->formRequestGenerators);
                $broadcastChannelsPublished = $runner->broadcastChannelsContent !== '';
                $broadcastEventsPublished = $runner->broadcastEventsIndexContent !== '' || $runner->broadcastEventsEchoContent !== '';

                outro("{$enumCount} enums, {$modelCount} models, {$resourceCount} resources, {$routeCount} routes, {$formRequestCount} form requests"
                    .($broadcastChannelsPublished ? ', broadcast channels' : '')
                    .($broadcastEventsPublished ? ', broadcast events' : '')
                    .' — All done');
            } else {
                outro('All done');
            }
        }

        return self::SUCCESS;
    }

    protected function runSource(string $source): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        Config::set('ts-publish.output_to_files', ! $preview);

        if (! $this->output->isQuiet()) {
            intro('ts:publish --source');
        }

        try {
            $runner = new RunnerForSource($source);
            $flags = $this->resolvePublishFlags();

            if ($flags === null) {
                return self::SUCCESS;
            }

            [
                $runner->shouldPublishEnums,
                $runner->shouldPublishModels,
                $runner->shouldPublishResources,
                $runner->shouldPublishRoutes,
                $runner->shouldPublishFormRequests,
                $runner->shouldPublishBroadcastChannels,
                $runner->shouldPublishBroadcastEvents,
            ] = $flags;
            $runner->run();
        } catch (InvalidArgumentException $e) {
            if (! $this->output->isQuiet()) {
                error($e->getMessage());
            }

            return self::FAILURE;
        }

        if (! $this->output->isQuiet()) {
            if ($preview) {
                $this->createPreview($runner);
            } else {
                $this->createPublishedFilesList($runner);
            }

            $enumCount = count($runner->enumGenerators);
            $modelCount = count($runner->modelGenerators);
            $resourceCount = count($runner->resourceGenerators);
            $routeCount = count($runner->routeGenerators);
            $formRequestCount = count($runner->formRequestGenerators);

            outro("{$enumCount} enums, {$modelCount} models, {$resourceCount} resources, {$routeCount} routes, {$formRequestCount} form requests — All done");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the final publish flags from config values and command options.
     *
     * @return array{0: bool, 1: bool, 2: bool, 3: bool, 4: bool, 5: bool, 6: bool}|null [shouldPublishEnums, shouldPublishModels, shouldPublishResources, shouldPublishRoutes, shouldPublishFormRequests, shouldPublishBroadcastChannels, shouldPublishBroadcastEvents] or null to abort
     */
    protected function resolvePublishFlags(): ?array
    {
        $onlyFunctional = (bool) $this->option('only-functional');

        // Publish-type registry in return-array order.
        // 'functional' controls inclusion under --only-functional (models/resources are excluded).
        /** @var array<string, array{config: string, option: string, label: string, functional: bool}> $types */
        $types = [
            'broadcast_channels' => ['config' => 'ts-publish.broadcast_channels.enabled', 'option' => 'only-broadcast-channels', 'label' => 'broadcast channels', 'functional' => true],
            'broadcast_events' => ['config' => 'ts-publish.broadcast_events.enabled', 'option' => 'only-broadcast-events', 'label' => 'broadcast events', 'functional' => true],
            'form_requests' => ['config' => 'ts-publish.form_requests.enabled', 'option' => 'only-form-requests', 'label' => 'form requests', 'functional' => true],
            'enums' => ['config' => 'ts-publish.enums.enabled', 'option' => 'only-enums', 'label' => 'enums', 'functional' => true],
            'models' => ['config' => 'ts-publish.models.enabled', 'option' => 'only-models', 'label' => 'models', 'functional' => false],
            'resources' => ['config' => 'ts-publish.resources.enabled', 'option' => 'only-resources', 'label' => 'resources', 'functional' => false],
            'routes' => ['config' => 'ts-publish.routes.enabled', 'option' => 'only-routes', 'label' => 'routes', 'functional' => true],
        ];

        // Build initial flags from config; --only-functional forces non-functional types off.
        /** @var array<string, bool> $flags */
        $flags = [];

        foreach ($types as $key => $type) {
            $flags[$key] = ($onlyFunctional && ! $type['functional'])
                ? false
                : Config::boolean($type['config']);
        }

        if ($onlyFunctional) {
            if (! in_array(true, $flags, true)) {
                if (! $this->output->isQuiet()) {
                    warning('All functional options are disabled in config. Nothing to publish.');
                }

                return null;
            }

            return [$flags['enums'], $flags['models'], $flags['resources'], $flags['routes'], $flags['form_requests'], $flags['broadcast_channels'], $flags['broadcast_events']];
        }

        // Find the active --only-* key, if any (validateOnlyOptions enforces at most one).
        $onlyKey = null;

        foreach ($types as $key => $type) {
            if ((bool) $this->option($type['option'])) {
                $onlyKey = $key;
                break;
            }
        }

        if ($onlyKey !== null) {
            $activeType = $types[$onlyKey];

            foreach (array_keys($flags) as $k) {
                $flags[$k] = false;
            }

            if (Config::boolean($activeType['config'])) {
                $flags[$onlyKey] = true;
            } else {
                $flags[$onlyKey] = $this->promptConfigOverride($activeType['label']);

                if (! $flags[$onlyKey]) {
                    return null;
                }
            }
        }

        if (! in_array(true, $flags, true)) {
            if (! $this->output->isQuiet()) {
                warning('Enums, models, resources, routes, form requests, broadcast channels, and broadcast events are all disabled in config. Nothing to publish.');
            }

            return null;
        }

        return [$flags['enums'], $flags['models'], $flags['resources'], $flags['routes'], $flags['form_requests'], $flags['broadcast_channels'], $flags['broadcast_events']];
    }

    protected function promptConfigOverride(string $type): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm("Config has {$type} publishing disabled. Override and publish {$type} anyway?", default: false);
    }

    protected function createPreview(Runner|RunnerForSource $runner): void
    {
        info('Previewing generated TypeScript declarations');

        if (count($runner->enumGenerators) > 0) {
            $this->newLine();
            $this->comment('Enums:');
            foreach ($runner->enumGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        if (count($runner->enumModularBarrels) > 0) {
            $this->newLine();
            $this->comment('Enum Barrel Files:');
            foreach ($runner->enumModularBarrels as $namespacePath => $content) {
                $this->newLine();
                $this->comment("  {$namespacePath}/index.ts");
                $this->line($content);
            }
        }

        if (count($runner->modelGenerators) > 0) {
            $this->newLine();
            $this->comment('Models:');
            foreach ($runner->modelGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        if (count($runner->modelModularBarrels) > 0) {
            $this->newLine();
            $this->comment('Model Barrel Files:');
            foreach ($runner->modelModularBarrels as $namespacePath => $content) {
                $this->newLine();
                $this->comment("  {$namespacePath}/index.ts");
                $this->line($content);
            }
        }

        if (count($runner->resourceGenerators) > 0) {
            $this->newLine();
            $this->comment('Resources:');
            foreach ($runner->resourceGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        if (count($runner->resourceModularBarrels) > 0) {
            $this->newLine();
            $this->comment('Resource Barrel Files:');
            foreach ($runner->resourceModularBarrels as $namespacePath => $content) {
                $this->newLine();
                $this->comment("  {$namespacePath}/index.ts");
                $this->line($content);
            }
        }

        if (count($runner->routeGenerators) > 0) {
            $this->newLine();
            $this->comment('Routes:');
            foreach ($runner->routeGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->transformer->namespacePath}/{$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        if (count($runner->routeModularBarrels) > 0) {
            $this->newLine();
            $this->comment('Route Barrel Files:');
            foreach ($runner->routeModularBarrels as $namespacePath => $content) {
                $this->newLine();
                $this->comment("  {$namespacePath}/index.ts");
                $this->line($content);
            }
        }

        if (count($runner->formRequestGenerators) > 0) {
            $this->newLine();
            $this->comment('Form Requests:');
            foreach ($runner->formRequestGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->transformer->namespacePath}/{$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }

        if (count($runner->formRequestModularBarrels) > 0) {
            $this->newLine();
            $this->comment('Form Request Barrel Files:');
            foreach ($runner->formRequestModularBarrels as $namespacePath => $content) {
                $this->newLine();
                $this->comment("  {$namespacePath}/index.ts");
                $this->line($content);
            }
        }

        if (! empty($runner->viteEnvContent)) {
            $viteEnvFilename = Config::string('ts-publish.vite_env.filename', 'vite-env.d.ts');
            $this->newLine();
            $this->comment('Vite Env:');
            $this->newLine();
            $this->comment("  {$viteEnvFilename}");
            $this->line($runner->viteEnvContent);
        }

        if (! empty($runner->inertiaConfigContent)) {
            $configFilename = Config::string('ts-publish.inertia.augmentation_filename');

            $this->newLine();
            $this->comment('Inertia Config:');
            $this->newLine();
            $this->comment("  {$configFilename}");
            $this->line($runner->inertiaConfigContent);
        }

        if (! empty($runner->broadcastChannelsContent)) {
            $filename = Config::string('ts-publish.broadcast_channels.filename', 'broadcast-channels.ts');
            $this->newLine();
            $this->comment('Broadcast Channels:');
            $this->newLine();
            $this->comment("  {$filename}");
            $this->line($runner->broadcastChannelsContent);
        }

        if (! empty($runner->broadcastEventsIndexContent)) {
            $filename = Config::string('ts-publish.broadcast_events.index_filename', 'broadcast-events.ts');
            $this->newLine();
            $this->comment('Broadcast Events:');
            $this->newLine();
            $this->comment("  {$filename}");
            $this->line($runner->broadcastEventsIndexContent);
        }

        if (count($runner->broadcastEventGenerators) > 0) {
            $this->newLine();
            $this->comment('Broadcast Event Interfaces:');
            foreach ($runner->broadcastEventGenerators as $generator) {
                $this->newLine();
                $this->comment("  {$generator->transformer->namespacePath}/{$generator->filename()}.ts");
                $this->line($generator->content);
            }
        }
        if (count($runner->broadcastEventModularBarrels) > 0) {
            $this->newLine();
            $this->comment('Broadcast Event Barrel Files:');
            foreach ($runner->broadcastEventModularBarrels as $namespacePath => $content) {
                $this->newLine();
                $this->comment("  {$namespacePath}/index.ts");
                $this->line($content);
            }
        }

        if (! empty($runner->broadcastEventsEchoContent)) {
            $filename = Config::string('ts-publish.broadcast_events.echo_augmentation.filename', 'echo-broadcast-events.d.ts');
            $this->newLine();
            $this->comment('Echo Broadcast Events:');
            $this->newLine();
            $this->comment("  {$filename}");
            $this->line($runner->broadcastEventsEchoContent);
        }
    }

    protected function createPublishedFilesList(Runner|RunnerForSource $runner): void
    {
        $outputDirectory = Config::string('ts-publish.output_directory');

        info("Published to: {$outputDirectory}");

        if ($this->output->isVerbose()) {
            $this->createVerboseFilesList($runner);
        } else {
            $this->createCompactSummary($runner);
        }
    }

    protected function createCompactSummary(Runner|RunnerForSource $runner): void
    {
        $enumCount = $runner->enumGenerators->count();
        $modelCount = $runner->modelGenerators->count();
        $resourceCount = $runner->resourceGenerators->count();
        $routeCount = $runner->routeGenerators->count();
        $formRequestCount = $runner->formRequestGenerators->count();

        $parts = [];

        if ($enumCount > 0) {
            $parts[] = Str::plural('enum', $enumCount, true);
        }

        if ($modelCount > 0) {
            $parts[] = Str::plural('model', $modelCount, true);
        }

        if ($resourceCount > 0) {
            $parts[] = Str::plural('resource', $resourceCount, true);
        }

        if ($routeCount > 0) {
            $parts[] = Str::plural('route controller', $routeCount, true);
        }

        if ($formRequestCount > 0) {
            $parts[] = Str::plural('form request', $formRequestCount, true);
        }

        if (count($parts) > 0) {
            $this->line('  '.implode(', ', $parts));
        }

        $extras = $this->collectExtras($runner);

        if (count($extras) > 0) {
            $grouped = collect($extras)->groupBy(fn (array $e) => $e[0]);
            $summary = $grouped->map(fn ($items, string $type) => $items->count() === 1
                ? Str::lower($type)
                : $items->count().' '.Str::lower(Str::plural($type, $items->count())),
            )->values()->implode(', ');

            $this->line("  Extras: {$summary}");
        }
    }

    protected function createVerboseFilesList(Runner|RunnerForSource $runner): void
    {
        if (count($runner->enumGenerators) > 0) {
            /** @var array<int, array<int, string>> $enumRows */
            $enumRows = $runner->enumGenerators->map(fn (EnumGenerator $g) => [
                $g->transformer->enumName,
                $g->filename().'.ts',
                (string) count($g->transformer->cases),
                (string) count($g->transformer->methods),
                (string) count($g->transformer->staticMethods),
            ])->toArray();

            table(
                headers: ['Enum', 'File', 'Cases', 'Methods', 'Static Methods'],
                rows: $enumRows,
            );
        }

        if (count($runner->modelGenerators) > 0) {
            /** @var array<int, array<int, string>> $modelRows */
            $modelRows = $runner->modelGenerators->map(fn (ModelGenerator $g) => [
                $g->transformer->modelName,
                $g->filename().'.ts',
                (string) count($g->transformer->columns),
                (string) count($g->transformer->mutators),
                (string) count($g->transformer->relations),
            ])->toArray();

            table(
                headers: ['Model', 'File', 'Columns', 'Mutators', 'Relations'],
                rows: $modelRows,
            );
        }

        if (count($runner->resourceGenerators) > 0) {
            /** @var array<int, array<int, string>> $resourceRows */
            $resourceRows = $runner->resourceGenerators->map(fn (ResourceGenerator $g) => [
                $g->transformer->resourceName,
                $g->filename().'.ts',
                (string) count($g->transformer->properties),
            ])->toArray();

            table(
                headers: ['Resource', 'File', 'Properties'],
                rows: $resourceRows,
            );
        }

        if (count($runner->routeGenerators) > 0) {
            /** @var array<int, array<int, string>> $routeRows */
            $routeRows = $runner->routeGenerators->map(fn (RouteGenerator $g) => [
                $g->transformer->controllerName,
                $g->transformer->namespacePath.'/'.$g->filename().'.ts',
                (string) count($g->transformer->actions),
            ])->toArray();

            table(
                headers: ['Controller', 'File', 'Actions'],
                rows: $routeRows,
            );
        }

        if (count($runner->formRequestGenerators) > 0) {
            /** @var array<int, array<int, string>> $formRequestRows */
            $formRequestRows = $runner->formRequestGenerators->map(fn (FormRequestGenerator $g) => [
                $g->transformer->typeName,
                $g->transformer->namespacePath.'/'.$g->filename().'.ts',
                (string) count($g->transformer->fields),
            ])->toArray();

            table(
                headers: ['Form Request', 'File', 'Fields'],
                rows: $formRequestRows,
            );
        }

        $extras = $this->collectExtras($runner);

        if (count($extras) > 0) {
            /** @var array<int, array<int, string>> $extras */
            table(
                headers: ['Type', 'File'],
                rows: $extras,
            );
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function collectExtras(Runner|RunnerForSource $runner): array
    {
        return array_filter([
            ...array_map(fn (string $path) => ['Enum Barrel', "{$path}/index.ts"], array_keys($runner->enumModularBarrels)),
            ...array_map(fn (string $path) => ['Model Barrel', "{$path}/index.ts"], array_keys($runner->modelModularBarrels)),
            ...array_map(fn (string $path) => ['Resource Barrel', "{$path}/index.ts"], array_keys($runner->resourceModularBarrels)),
            ...array_map(fn (string $path) => ['Route Barrel', "{$path}/index.ts"], array_keys($runner->routeModularBarrels)),
            ...array_map(fn (string $path) => ['Form Request Barrel', "{$path}/index.ts"], array_keys($runner->formRequestModularBarrels)),
            ...array_map(fn (string $path) => ['Broadcast Event Barrel', "{$path}/index.ts"], array_keys($runner->broadcastEventModularBarrels)),
            $runner->globalsContent ? ['Globals', Config::string('ts-publish.globals.filename')] : null,
            $runner->viteEnvContent ? ['Vite Env', Config::string('ts-publish.vite_env.filename', 'vite-env.d.ts')] : null,
            $runner->inertiaConfigContent ? ['Inertia Config', Config::string('ts-publish.inertia.augmentation_filename')] : null,
            $runner->broadcastChannelsContent !== ''
                ? ['Broadcast Channels', Config::string('ts-publish.broadcast_channels.filename', 'broadcast-channels.ts')]
                : null,
            $runner->broadcastEventsIndexContent !== ''
                ? ['Broadcast Events', Config::string('ts-publish.broadcast_events.index_filename', 'broadcast-events.ts')]
                : null,
            $runner->broadcastEventsEchoContent !== ''
                ? ['Echo Broadcast Events', Config::string('ts-publish.broadcast_events.echo_augmentation.filename', 'echo-broadcast-events.d.ts')]
                : null,
            $runner->jsonContent ? ['JSON', Config::string('ts-publish.json.filename')] : null,
        ]);
    }
}
