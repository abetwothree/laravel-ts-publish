<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\RoutesCollector;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\RouteGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\EnumWriter;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use AbeTwoThree\LaravelTsPublish\Writers\JsonWriter;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use AbeTwoThree\LaravelTsPublish\Writers\RouteWriter;
use AbeTwoThree\LaravelTsPublish\Writers\WatcherJsonWriter;

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

    'barrel_writer_class' => BarrelWriter::class,

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
        'writer_class' => WatcherJsonWriter::class,
        'filename' => 'laravel-ts-collected-files.json',
        /* Defaults to output_directory setting */
        'output_directory' => null,
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
        'collector_class' => EnumsCollector::class,
        'generator_class' => EnumGenerator::class,
        'transformer_class' => EnumTransformer::class,
        'writer_class' => EnumWriter::class,
        'template' => 'laravel-ts-publish::enum',
        'additional_directories' => [],
        'included' => [],
        'excluded' => [],
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
        'relation_nullability_map' => [
            // \Illuminate\Database\Eloquent\Relations\BelongsTo::class => 'nullable',
            // \Illuminate\Database\Eloquent\Relations\HasOne::class    => 'never',
        ],
        'namespace' => 'models',
        'collector_class' => ModelsCollector::class,
        'generator_class' => ModelGenerator::class,
        'transformer_class' => ModelTransformer::class,
        'writer_class' => ModelWriter::class,
        'template' => 'laravel-ts-publish::model-split',
        'additional_directories' => [],
        'included' => [],
        'excluded' => [],
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
        'collector_class' => ResourcesCollector::class,
        'generator_class' => ResourceGenerator::class,
        'transformer_class' => ResourceTransformer::class,
        'writer_class' => ResourceWriter::class,
        'template' => 'laravel-ts-publish::resource',
        'additional_directories' => [],
        'included' => [],
        'excluded' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, TypeScript files are generated for each controller group
    | found in the router. Each file exports a defineRoute() call per action.
    |
    | 'method_casing': Case style for generated JS export names ('camel', 'snake', or 'pascal').
    | 'output_path': Output directory for generated route files. Defaults to {output_directory} when null.
    | 'only': Pattern list - only publish routes matching any pattern (supports wildcards).
    | 'except': Pattern list - skip routes matching any pattern (supports wildcards and ! negation).
    | 'exclude_middleware': Skip routes behind any of these middleware.
    | 'only_named': When true, only publish named routes.
    */

    'routes' => [
        'enabled' => true,
        'method_casing' => 'camel',
        'output_path' => null,
        'only' => [],
        'except' => [],
        'exclude_middleware' => [],
        'only_named' => false,
        'collector_class' => RoutesCollector::class,
        'generator_class' => RouteGenerator::class,
        'transformer_class' => RouteTransformer::class,
        'writer_class' => RouteWriter::class,
        'template' => 'laravel-ts-publish::route',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inertia Page Types
    |--------------------------------------------------------------------------
    |
    | When enabled, generates TypeScript page-prop types for each Inertia
    | component detected via laravel/ranger static analysis, and a TypeScript Declaration
    | module augmentation file for @inertiajs/core.
    |
    | Requires inertiajs/inertia-laravel:^3 to be installed.
    | Static analysis also relies on laravel/ranger (and its transitive dependencies
    | laravel/surveyor and spatie/php-structure-discoverer), which is already
    | declared as a package dependency.
    |
    | 'component_casing': Case style for generated component map keys ('camel', 'snake', or 'pascal').
    | 'inertia_middleware_path': Optional path to scan for Inertia middleware. Defaults to app/ if not set or invalid.
    | 'augmentation_filename': Filename for the generated module augmentation file. Defaults to inertia-config.d.ts.
    | 'output_path': Directory for the declaration file. Defaults to routes output_path.
    */

    'inertia' => [
        'enabled' => false,
        'component_casing' => 'camel',
        'inertia_middleware_path' => null,
        'augmentation_filename' => 'inertia-config.d.ts',
        'output_path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Globals Declaration Types
    |--------------------------------------------------------------------------
    |
    | Specifies whether to create a "global.ts" file with a global namespace containing all generated types.
    */

    'globals' => [
        'enabled' => false,
        'writer_class' => GlobalsWriter::class,
        'filename' => 'laravel-ts-global.ts',
        /* Defaults to output_directory setting */
        'output_directory' => null,
        'template' => 'laravel-ts-publish::globals',
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Output
    |--------------------------------------------------------------------------
    |
    | Specifies whether to output the generated TypeScript definitions in a JSON file.
    | This can be in addition to or instead of outputting to .d.ts files, depending on the "output_to_files" option.
    */

    'json' => [
        'enabled' => false,
        'writer_class' => JsonWriter::class,
        'filename' => 'laravel-ts-definitions.json',
        /* Defaults to output_directory setting */
        'output_directory' => null,
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
        'enabled' => false,
        'filename' => 'vite-env.d.ts',
        'output_path' => null,
        'source_file' => null,
    ],
];
