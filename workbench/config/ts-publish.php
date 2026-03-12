<?php

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\EnumWriter;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use AbeTwoThree\LaravelTsPublish\Writers\JsonWriter;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;
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
    | File Collector Classes
    |--------------------------------------------------------------------------
    |
    | Specifies the classes responsible for finding the enums & models
    | You can extend the default collectors or create your own.
    |
    | Collectors find the given class types and pas the results to generators.
    */
    'model_collector_class' => ModelsCollector::class,

    'enum_collector_class' => EnumsCollector::class,

    /*
    |--------------------------------------------------------------------------
    | File Generator Classes
    |--------------------------------------------------------------------------
    |
    | Specifies the classes responsible for generating the TypeScript output.
    | You can extend the default generators or create your own.
    |
    | Generators receive a collected class, pass it to its transformer,
    | and then pass the transformer to the writer to create the final output.
    */
    'model_generator_class' => ModelGenerator::class,

    'enum_generator_class' => EnumGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | File Transformer Classes
    |--------------------------------------------------------------------------
    |
    | Specifies the classes responsible for transforming PHP classes into TypeScript definitions.
    | You can extend the default transformers or create your own.
    |
    | Transformers receive a class and transform it into a TypeScript definition.
    */
    'model_transformer_class' => ModelTransformer::class,

    'enum_transformer_class' => EnumTransformer::class,

    /*
    |--------------------------------------------------------------------------
    | File Writers Classes
    |--------------------------------------------------------------------------
    |
    | Specifies the classes responsible for writing the TypeScript definitions to files.
    | You can extend the default writers or create your own.
    |
    | Writers receive a transformer and optionally write the output to a file and return it as a string.
    |
    | The barrel writer creates an index.ts file that exports all generated types from each file category
    |
    | The globals writer creates a global.d.ts file that contains a global namespace with all generated types from all categories
    */
    'model_writer_class' => ModelWriter::class,

    'enum_writer_class' => EnumWriter::class,

    'barrel_writer_class' => BarrelWriter::class,

    'globals_writer_class' => GlobalsWriter::class,

    'json_writer_class' => JsonWriter::class,

    'watcher_json_writer_class' => WatcherJsonWriter::class,

    /*
    |--------------------------------------------------------------------------
    | File Template Blade Files
    |--------------------------------------------------------------------------
    |
    | Specifies the Blade template files responsible for generating the TypeScript definitions.
    | You can extend the default templates or create your own.
    |
    | Writers pass the transformed data to the templates to generate the actual TypeScript content.
    |
    | The easiest way to customize the output is to publish the templates and modify them as needed.
    */
    'model_template' => 'laravel-ts-publish::model-split',

    'enum_template' => 'laravel-ts-publish::enum',

    'globals_template' => 'laravel-ts-publish::globals',

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
    | To configure TypeScript types on a per-model per-enum basis use attributes.
    | See AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
    */
    'custom_ts_mappings' => [
        // 'binary' => 'Blob',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enum Public Methods Global Inclusion
    |--------------------------------------------------------------------------
    |
    | By default this package requires that you use the PHP method attributes
    | (#[TsEnumMethod] & #[TsEnumStaticMethod]) to include them in the output.
    |
    | This is done for security reasons to avoid making private content public.
    |
    | However, you can make all enum methods be included by default without
    | needing to add the attributes by setting the options below to true.
    |
    | Even when you set these options as true, you can still use the
    | attributes to set custom settings your enum on methods.
    |
    | If you set to true, I hope you know what you're doing.
    */

    'auto_include_enum_methods' => false,

    'auto_include_enum_static_methods' => false,

    /*
    |--------------------------------------------------------------------------
    | Output TypeScript Definitions to Files
    |--------------------------------------------------------------------------
    |
    | Specifies whether to output the TypeScript definitions to files.
    |
    | This will write "enums" & "models" folders with a file for each class.
    | It will create a "barrel" index.ts file to export all types.
    |
    | You can conditionally only publish enums or models by setting the options below.
    */
    'output_to_files' => true,

    'output_directory' => resource_path('/js/types/'),

    'publish_enums' => true,

    'publish_models' => true,

    /*
    |--------------------------------------------------------------------------
    | Modular Publishing
    |--------------------------------------------------------------------------
    |
    | When enabled, TypeScript files are organized into namespace-derived
    | directory trees instead of flat "enums/" and "models/" folders.
    |
    | For example, `Blog\Enums\ArticleStatus` writes to `blog/enums/article-status.ts`
    | instead of `enums/article-status.ts`.
    |
    | Each namespace folder receives its own barrel index.ts file.
    |
    | Import paths between generated files are computed as relative paths
    | based on the namespace directory structure.
    */
    'modular_publishing' => false,

    /*
    |--------------------------------------------------------------------------
    | Namespace Strip Prefix
    |--------------------------------------------------------------------------
    |
    | When modular publishing is enabled, this prefix is stripped from the
    | fully-qualified class namespace before converting to a directory path.
    |
    | For example, setting this to 'Modules\\' turns
    | `Modules\Blog\Models\Article` into `blog/models/article.ts`
    | instead of `modules/blog/models/article.ts`.
    */
    'namespace_strip_prefix' => '',

    /*
    |--------------------------------------------------------------------------
    | Publishing Case Style
    |--------------------------------------------------------------------------
    |
    | Specifies the case style to use for relationship names & enum methods in the generated TypeScript definitions.
    |
    | Can be 'snake', 'camel', or 'pascal'.
    */

    'relationship_case' => 'snake',

    'enum_method_case' => 'camel',

    /*
    |--------------------------------------------------------------------------
    | Use Type-Only Imports
    |--------------------------------------------------------------------------
    |
    | When enabled, model type imports use `import type { ... }` instead of
    | `import { ... }`. This is required by stricter TypeScript setups that
    | enable `verbatimModuleSyntax` or `isolatedModules`.
    |
    | Only affects model file imports (enum types, model interfaces, custom
    | TsCasts imports). The enum `defineEnum` value import is unaffected.
    */

    'use_type_imports' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate TypeScript Global Namespace Types
    |--------------------------------------------------------------------------
    |
    | Specifies whether to create a "global.d.ts" file with a global namespace containing all generated types.
    */
    'output_globals_file' => false,

    'global_filename' => 'laravel-ts-global.d.ts',

    /* Defaults to output_directory setting */
    'global_directory' => null,

    'models_namespace' => 'models',

    'enums_namespace' => 'enums',

    /*
    |--------------------------------------------------------------------------
    | Output the Results in a JSON File
    |--------------------------------------------------------------------------
    |
    | Specifies whether to output the generated TypeScript definitions in a JSON file.
    | This can be in addition to or instead of outputting to .d.ts files, depending on the "output_to_files" option.
    */
    'output_json_file' => false,

    'json_filename' => 'laravel-ts-definitions.json',

    /* Defaults to output_directory setting */
    'json_output_directory' => null,

    /*
    |--------------------------------------------------------------------------
    | Output files collected list in a JSON file
    |--------------------------------------------------------------------------
    |
    | Specifies whether to create a JSON file containing the list of collected models and enums file paths.
    | This is useful for npm processes to watch for changes in the collected files and trigger the publish command on change.
    */
    'output_collected_files_json' => true,

    'collected_files_json_filename' => 'laravel-ts-collected-files.json',

    /* Defaults to output_directory setting */
    'collected_files_json_output_directory' => null,

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
    | Model Collector Finder Settings
    |--------------------------------------------------------------------------
    |
    | Below you can specify which models to include, exclude, or add additional directories to search for models in.
    | By default, the package will look for models in the app/Models directory and include all models found there.
    |
    | Settings can be specific model class names or directories to search for models in. For example:
    | 'included_models' => [
    |     'App\Models\User', // Include only the User model
    |     'App\Models\Post', // Include only the Post model
    |     'vendor/<vendor_name>/src/Models', // Include all models in the vendor/<vendor_name>/src/Models directory
    | ],
    */

    /* Most flexible, anything added here will be included */
    'additional_model_directories' => [
        //
    ],

    /* Most restrictive, only these models will be included */
    'included_models' => [
        //
    ],

    /* Excluded models will always be ignored */
    'excluded_models' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Enum Collector Finder Settings
    |--------------------------------------------------------------------------
    |
    | Below you can specify which enums to include, exclude, or add additional directories to search for enums in.
    | By default, the package will look for enums in the app/Enums directory and include all enums found there.
    |
    | Settings can be specific enum class names or directories to search for enums in. For example:
    | 'included_enums' => [
    |     'App\Enums\UserType', // Include only the UserType enum
    |     'App\Enums\PostStatus', // Include only the PostStatus enum
    |     'vendor/<vendor_name>/src/Enums', // Include all enums in the vendor/<vendor_name>/src/Enums directory
    | ],
    */

    /* Most flexible, anything added here will be included */
    'additional_enum_directories' => [
        //
    ],

    /* Most restrictive, only these enums will be included */
    'included_enums' => [
        //
    ],

    /* Excluded enums will always be ignored */
    'excluded_enums' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Enum Metadata & Tolki Enum Package Integration
    |--------------------------------------------------------------------------
    |
    | Specifies whether to include metadata about the enums which is used by the @tolki/enum package.
    |
    | If you don't plan on using the @tolki/enum package or don't need the additional metadata, set 'enum_metadata_enabled' to false.
    |
    | When this is enabled, each enum will include the following additional properties:
    | - _cases: An array of the enum case names, used to know which keys are cases.
    | - _methods: An array of the enum method names, used to know which keys are methods.
    | - _static: An array of the enum static method names, used to know which keys are static methods.
    |
    | When 'enums_use_tolki_package' is enabled, it will bind helper methods to the generated enum.
    | The helper methods come from the @tolki/enum package to implement similar methods that PHP provides on enums.
    */

    'enum_metadata_enabled' => true,

    'enums_use_tolki_package' => true,
];
