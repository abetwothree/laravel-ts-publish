<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Run After Migrate
    |--------------------------------------------------------------------------
    |
    | Specifies whether to recreate TypeScript declaration files after running migrations.
    |
    | Ignore if not outputting to files
    */

    'run_after_migrate' => env('TS_PUBLISH_RUN_AFTER_MIGRATE', true),

    /*
    |--------------------------------------------------------------------------
    | Output TypeScript Definitions to Files
    |--------------------------------------------------------------------------
    |
    | Specifies whether to output the TypeScript definitions to files.
    |
    | This will write modular namespace-derived directory trees with a file
    | for each class and barrel index.ts files per namespace directory.
    */

    'output_to_files' => true,

    'output_directory' => resource_path('/js/types/data/'),

    /*
    |--------------------------------------------------------------------------
    | Generation Cache
    |--------------------------------------------------------------------------
    |
    | After the first full publish, ts:publish can skip re-generating classes
    | whose source files (and their dependencies) have not changed. The cache
    | is busted automatically when the package version or the output-affecting
    | config changes, and can be forced fresh with `php artisan ts:publish --fresh`.
    |
    | `store` selects where the manifest is kept:
    |   - null  → file driver, written under `directory` below
    |   - 'redis' | 'database' | any Laravel cache store name → that store
    |
    | `--source=` runs always bypass the cache.
    */

    'cache' => [
        'enabled' => env('TS_PUBLISH_CACHE_ENABLED', true),
        'store' => env('TS_PUBLISH_CACHE_STORE'),
        'directory' => storage_path('framework/cache/ts-publish'),
        'key' => env('TS_PUBLISH_CACHE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespace Strip Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix is stripped from the fully-qualified class namespace before
    | converting to a directory path.
    |
    | For example, setting this to 'Modules\\' turns
    | `Modules\Blog\Models\Article` into `blog/models/article.ts`
    | instead of `modules/blog/models/article.ts`.
    */

    'namespace_strip_prefix' => '',

    /*
    |--------------------------------------------------------------------------
    | Global TypeScript Mappings
    |--------------------------------------------------------------------------
    |
    | Here you can override the default global TypeScript type mappings or
    | add new mappings for custom types used in your models, enums, or resources.
    |
    | See AbeTwoThree\LaravelTsPublish\TypeScriptMap
    |
    | To configure TypeScript types on a per-class basis use attributes.
    | See AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
    */

    'custom_ts_mappings' => [
        // 'binary' => 'Blob',
    ],

    /*
    |--------------------------------------------------------------------------
    | Map Timestamps as Date Object Types
    |--------------------------------------------------------------------------
    |
    | Specifies whether to map timestamp fields as Date objects in the
    | generated TypeScript definitions instead of strings.
    */

    'timestamps_as_date' => false,

    /*
    |--------------------------------------------------------------------------
    | Interface Extending
    |--------------------------------------------------------------------------
    |
    | Specify TypeScript interfaces that all generated model or resource
    | interfaces should extend. These are applied globally to every
    | published interface of the given type.
    |
    | Each entry can be a plain string (simple extends) or an array with
    | 'extends', 'import', and optionally 'types' keys.
    |
    | For per-class extends, use the #[TsExtends] attribute instead.
    |
    | 'ts_extends' => [
    |     'models' => [
    |         'HasTimestamps',
    |         ['extends' => 'BaseFields', 'import' => '@/types/base'],
    |         ['extends' => 'Pick<Auditable, "created_by">', 'import' => '@/types/audit', 'types' => ['Auditable']],
    |     ],
    |     'resources' => [
    |         ['extends' => 'BaseResource', 'import' => '@/types/base'],
    |     ],
    | ],
    */

    'ts_extends' => [
        'broadcast_events' => [
            //
        ],
        'form_requests' => [
            //
        ],
        'models' => [
            //
        ],
        'resources' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Writer Classes
    |--------------------------------------------------------------------------
    |
    | The barrel writer creates an index.ts file that exports all generated
    | types from each file category.
    */

    // 'barrel_writer_class' => BarrelWriter::class,

    /*
    |--------------------------------------------------------------------------
    | Vite Watcher JSON
    |--------------------------------------------------------------------------
    |
    | Specifies whether to create a JSON file containing the list of collected models and enums file paths.
    | This is useful for npm processes to watch for changes in the collected files and trigger the publish command on change.
    */

    'watcher' => [
        'enabled' => true,
        'filename' => 'laravel-ts-collected-files.json',
        'output_directory' => '',
        // 'writer_class' => WatcherJsonWriter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Enums
    |--------------------------------------------------------------------------
    |
    | Settings for enum publishing and the @tolki/ts package integration.
    |
    | When 'metadata_enabled' is true, each enum includes _cases, _methods,
    | and _static arrays for runtime introspection.
    |
    | When 'use_tolki_package' is true, enums are wrapped in defineEnum()
    | from @tolki/ts to bind PHP-like helper methods at runtime.
    */

    'enums' => [
        'enabled' => true,
        'metadata_enabled' => true,
        'use_tolki_package' => true,
        'auto_include_methods' => false,
        'auto_include_static_methods' => false,
        'method_case' => 'camel',
        'namespace' => 'enums',
        'template' => 'laravel-ts-publish::enum',
        'additional_directories' => [],
        'included' => [],
        'excluded' => [],
        // 'collector_class' => EnumsCollector::class,
        // 'generator_class' => EnumGenerator::class,
        // 'transformer_class' => EnumTransformer::class,
        // 'writer_class' => EnumWriter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Settings for model publishing.
    |
    | 'relationship_case': Case style for relationship names ('snake', 'camel', or 'pascal').
    | 'nullable_relations': When enabled, singular relations generate types with | null.
    */

    'models' => [
        'enabled' => true,
        'relationship_case' => 'snake',
        'nullable_relations' => true,
        'namespace' => 'models',
        'template' => 'laravel-ts-publish::model-split',
        'relation_nullability_map' => [
            // \Illuminate\Database\Eloquent\Relations\BelongsTo::class => 'nullable',
            // \Illuminate\Database\Eloquent\Relations\HasOne::class    => 'never',
        ],
        'additional_directories' => [],
        'included' => [],
        'excluded' => [],
        // 'collector_class' => ModelsCollector::class,
        // 'generator_class' => ModelGenerator::class,
        // 'transformer_class' => ModelTransformer::class,
        // 'writer_class' => ModelWriter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Settings for API resource publishing.
    */

    'resources' => [
        'enabled' => true,
        'namespace' => 'resources',
        'template' => 'laravel-ts-publish::resource',
        'additional_directories' => [],
        'included' => [],
        'excluded' => [],
        // 'collector_class' => ResourcesCollector::class,
        // 'generator_class' => ResourceGenerator::class,
        // 'transformer_class' => ResourceTransformer::class,
        // 'writer_class' => ResourceWriter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, TypeScript files are generated for each controller found
    | in your application. Each file exports a defineRoute() call per method.
    */

    'routes' => [
        'enabled' => true,
        'method_casing' => 'camel',
        'only_named' => false,
        'template' => 'laravel-ts-publish::route',
        'output_directory' => '',
        'only' => [],
        'except' => [],
        'exclude_middleware' => [],
        // 'collector_class' => RoutesCollector::class,
        // 'generator_class' => RouteGenerator::class,
        // 'transformer_class' => RouteTransformer::class,
        // 'writer_class' => RouteWriter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Request Types
    |--------------------------------------------------------------------------
    |
    | When enabled, generates a TypeScript interface for each FormRequest class
    | found in the application. Rules are resolved statically where possible;
    | requests with dynamic rules fall back to `Record<string, unknown>`.
    */

    'form_requests' => [
        'enabled' => true,
        'namespace' => 'form-requests',
        'template' => 'laravel-ts-publish::form-request',
        'output_directory' => '',
        'additional_directories' => [],
        'included' => [],
        'excluded' => [],
        // 'collector_class' => FormRequestsCollector::class,
        // 'generator_class' => FormRequestGenerator::class,
        // 'transformer_class' => FormRequestTransformer::class,
        // 'writer_class' => FormRequestWriter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Channel Types
    |--------------------------------------------------------------------------
    |
    | When enabled, generates a single broadcast-channels.ts file containing:
    |   - A BroadcastChannel union type (template literal union of all channel names).
    |   - A BroadcastChannels const with nested accessor functions matching the
    |     dot-notation structure of each channel name.
    */

    'broadcast_channels' => [
        'enabled' => true,
        'filename' => 'broadcast-channels.ts',
        'template' => 'laravel-ts-publish::broadcast-channels',
        'output_directory' => '',
        // 'collector_class' => BroadcastChannelsCollector::class,
        // 'writer_class' => BroadcastChannelsWriter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Event Types
    |--------------------------------------------------------------------------
    |
    | When enabled, generates one TypeScript interface file per ShouldBroadcast
    | event class, a broadcast-events.ts index file with a BroadcastEvent union
    | type and flat BroadcastEvents const.
    |
    | Optionally, you can also generate an echo-broadcast-events.d.ts Echo module
    | TypeScript augmentation file. Only works if you're using the any of the
    | npm `@laravel/echo-{vue,react,svelte}` packages for broadcast events.
    */

    'broadcast_events' => [
        'enabled' => true,
        'index_filename' => 'broadcast-events.ts',
        'index_template' => 'laravel-ts-publish::broadcast-events-index',
        'template' => 'laravel-ts-publish::broadcast-event',
        'output_directory' => '',
        'additional_directories' => [],
        'included' => [],
        'excluded' => [],
        // 'collector_class' => BroadcastEventsCollector::class,
        // 'generator_class' => BroadcastEventGenerator::class,
        // 'transformer_class' => BroadcastEventTransformer::class,
        // 'writer_class' => BroadcastEventWriter::class,
        // 'index_writer_class' => BroadcastEventsIndexWriter::class,

        'echo_augmentation' => [
            'enabled' => true,
            'echo_package' => null,
            'filename' => 'echo-broadcast-events.d.ts',
            'template' => 'laravel-ts-publish::echo-broadcast-events',
            'output_directory' => '',
            // 'writer_class' => BroadcastEventsEchoWriter::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inertia Page Types
    |--------------------------------------------------------------------------
    |
    | When enabled, generates TypeScript page-prop types for each Inertia component
    | and a TypeScript Declaration module augmentation file for @inertiajs/core.
    */

    'inertia' => [
        'enabled' => true,
        'component_casing' => 'camel',
        'inertia_middleware_path' => null,
        'augmentation_filename' => 'inertia-config.d.ts',
        'output_directory' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Vite Environment Types
    |--------------------------------------------------------------------------
    |
    | When enabled, reads .env.example (or the specified source file) for
    | VITE_-prefixed variables and writes a vite-env.d.ts declaration file
    | that augments Vite's ImportMetaEnv interface.
    |
    | All variables are typed as string (Vite always provides strings at runtime).
    | Source defaults to .env first, then falls back to .env.example if .env doesn't exist.
    */

    'vite_env' => [
        'enabled' => true,
        'filename' => 'vite-env.d.ts',
        'source_file' => null,
        'output_directory' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Globals Declaration Types
    |--------------------------------------------------------------------------
    |
    | Specifies whether to create a "global.ts" file with a global namespace
    | which will contain all generated types for individually generated interfaces.
    */

    'globals' => [
        'enabled' => false,
        'filename' => 'laravel-ts-global.ts',
        'template' => 'laravel-ts-publish::globals',
        'output_directory' => '',
        // 'writer_class' => GlobalsWriter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Output
    |--------------------------------------------------------------------------
    |
    | Specifies whether to output the generated TypeScript definitions in a JSON file.
    */

    'json' => [
        'enabled' => false,
        'filename' => 'laravel-ts-definitions.json',
        'output_directory' => '',
        // 'writer_class' => JsonWriter::class,
    ],
];
