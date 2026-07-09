<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Commands;

use AbeTwoThree\LaravelTsPublish\Cache\CacheBootstrap;
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

use function Laravel\Prompts\callout;
use function Laravel\Prompts\confirm;

use Laravel\Prompts\Elements\Element;
use Laravel\Prompts\Elements\ElementContract;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;

use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\table;
use function Laravel\Prompts\task;
use function Laravel\Prompts\warning;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TsPublishCommand extends Command
{
    protected float $startedAt = 0.0;

    protected $signature = 'ts:publish
        {--preview=false : Output generated TypeScript declarations to the console instead of writing to files}
        {--source= : FQCN or file path of a specific supported class to republish}
        {--fresh : Ignore and rebuild the generation cache from scratch (no-op with --source or --preview)}
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
        $this->startedAt = microtime(true);
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

        $onlyBroadcastChannels = (bool) $this->option('only-broadcast-channels');
        $onlyBroadcastEvents = (bool) $this->option('only-broadcast-events');
        $onlyEnums = (bool) $this->option('only-enums');
        $onlyFormRequests = (bool) $this->option('only-form-requests');
        $onlyModels = (bool) $this->option('only-models');
        $onlyResources = (bool) $this->option('only-resources');
        $onlyRoutes = (bool) $this->option('only-routes');

        $onlyCount = (int) $onlyEnums + (int) $onlyModels + (int) $onlyResources + (int) $onlyRoutes + (int) $onlyFormRequests + (int) $onlyBroadcastChannels + (int) $onlyBroadcastEvents;

        if ($onlyCount > 1) {
            $this->reportError('Cannot use multiple --only-* options together. Please specify only one or none of these options.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Report a failure message so it stays visible under --quiet.
     *
     * Laravel Prompts renders to stdout at normal verbosity, which --quiet
     * suppresses entirely. Non-interactive callers such as the Vite plugin
     * pass --quiet and would otherwise only receive a bare exit code, so
     * quiet failures are written to stderr with the QUIET verbosity flag.
     */
    protected function reportError(string $message): void
    {
        if (! $this->output->isQuiet()) {
            error($message);

            return;
        }

        $output = $this->output->getOutput();

        $errorOutput = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;

        $errorOutput->writeln("ts:publish failed: {$message}", OutputInterface::VERBOSITY_QUIET);
    }

    /**
     * Display a one-line run context: mode, output directory, and cache state.
     */
    protected function showRunContext(string $mode): void
    {
        $output = Config::string('ts-publish.output_directory');
        $cache = CacheBootstrap::enabled()
            ? ($this->option('fresh') ? 'rebuilding' : ($this->option('source') ? 'disabled' : 'enabled'))
            : 'disabled';

        note("Mode: {$mode}  ·  Cache: {$cache}  ·  Output: {$output}");
    }

    protected function runAll(): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        Config::set('ts-publish.output_to_files', ! $preview);

        if (! $this->output->isQuiet()) {
            intro('ts:publish');
            $this->showRunContext($preview ? 'preview' : 'full publish');
        }

        $runner = resolve(Runner::class);

        // Attach the generation cache for real (file-writing) runs only. Preview
        // runs write nothing, so caching them would record empty outputs and
        // poison later real runs into skipping files that were never written.
        if (! $preview && CacheBootstrap::enabled()) {
            $repository = CacheBootstrap::repository();

            if ($this->option('fresh')) {
                $repository->flush();
            }

            $runner->useCache(CacheBootstrap::manifest($repository));
        }

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
            if ($this->output->isQuiet()) {
                $runner->run();
            } else {
                task(
                    label: 'Generating TypeScript files',
                    callback: function (Logger $logger) use ($runner): bool {
                        $runner->setLogger($logger);
                        $runner->run();

                        return true;
                    },
                    keepSummary: true,
                );
            }
        } catch (InvalidArgumentException $e) {
            $this->reportError($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->output->isQuiet()) {
            if ($preview) {
                $this->createPreview($runner);
            } else {
                $this->createPublishedFilesList($runner);
            }

            outro('All done');
        }

        return self::SUCCESS;
    }

    protected function runSource(string $source): int
    {
        $preview = filter_var($this->option('preview'), FILTER_VALIDATE_BOOLEAN);
        Config::set('ts-publish.output_to_files', ! $preview);

        if (! $this->output->isQuiet()) {
            intro('ts:publish --source');
            $this->showRunContext($preview ? "preview · source={$source}" : "source={$source}");
        }

        try {
            // --source runs always bypass the generation cache.
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
            $this->reportError($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->output->isQuiet()) {
            if ($preview) {
                $this->createPreview($runner);
            } else {
                $this->createPublishedFilesList($runner);
            }

            outro('All done');
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
        }

        $this->renderSummaryCallout($runner);
    }

    protected function createCompactSummary(Runner|RunnerForSource $runner): void
    {
        $this->renderSummaryCallout($runner);
    }

    /**
     * Ordered [singular-label => count] map of the primary published types, omitting
     * zero counts. The singular label is pluralized by `Str::plural(..., prependCount: true)`
     * at render time (e.g. 'model' → "128 models", "1 model").
     *
     * @return array<string, int>
     */
    protected function primaryCounts(Runner|RunnerForSource $runner): array
    {
        return array_filter([
            'enum' => $runner->enumGenerators->count(),
            'model' => $runner->modelGenerators->count(),
            'resource' => $runner->resourceGenerators->count(),
            'route controller' => $runner->routeGenerators->count(),
            'form request' => $runner->formRequestGenerators->count(),
            'broadcast event' => $runner->broadcastEventGenerators->count(),
        ], fn (int $count) => $count > 0);
    }

    /**
     * Total number of TypeScript files written (primary files + extras).
     */
    protected function totalFilesWritten(Runner|RunnerForSource $runner): int
    {
        return array_sum($this->primaryCounts($runner)) + count($this->collectExtras($runner));
    }

    /**
     * Render the published-files summary as a scannable callout: a key/value list
     * of type counts, a bulleted list of extras, and a footer with the file total
     * and elapsed time.
     */
    protected function renderSummaryCallout(Runner|RunnerForSource $runner): void
    {
        /** @var list<string> $primaryLines */
        $primaryLines = [];

        foreach ($this->primaryCounts($runner) as $singular => $count) {
            // prependCount: true → auto-pluralized AND count-prefixed: "128 models", "1 resource".
            $primaryLines[] = Str::plural($singular, $count, prependCount: true);
        }

        /** @var list<string|ElementContract> $content */
        $content = [];

        if ($primaryLines !== []) {
            $content[] = Element::bulletedList($primaryLines);
        }

        // Extras: a singleton (e.g. "vite env", "inertia config") shows no count;
        // multiples are pluralized + counted (e.g. "38 model barrels").
        $extras = $this->collectExtras($runner);

        if ($extras !== []) {
            $grouped = collect($extras)->groupBy(fn (array $e) => $e[0]);
            $extraLines = $grouped->map(fn ($items, string $type) => $items->count() === 1
                ? Str::lower($type)
                : Str::plural(Str::lower($type), $items->count(), prependCount: true),
            )->values()->all();

            $content[] = Element::heading('Extras');
            $content[] = Element::bulletedList($extraLines);
        }

        if ($content === []) {
            return;
        }

        $elapsed = number_format(microtime(true) - $this->startedAt, 2);
        $total = $this->totalFilesWritten($runner);

        callout(
            label: 'Published TypeScript files',
            content: $content,
            info: Str::plural('file', $total, prependCount: true)." · {$elapsed}s",
        );
    }

    protected function createVerboseFilesList(Runner|RunnerForSource $runner): void
    {
        if (count($runner->enumGenerators) > 0) {
            note('Enums');

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
            note('Models');

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
            note('Resources');

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
            note('Routes');

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
            note('Form Requests');

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
            note('Extras');

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
