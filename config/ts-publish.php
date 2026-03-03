<?php

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use AbeTwoThree\LaravelTsPublish\Writers\EnumWriter;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;

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
    'run_after_migrate' => env('TS_PUBLISHER_RUN_AFTER_MIGRATE', false),

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
    */
    'model_writer_class' => ModelWriter::class,

    'enum_writer_class' => EnumWriter::class,

    'barrel_writer_class' => BarrelWriter::class,

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
    'model_template' => 'laravel-ts-publish::model',

    'enum_template' => 'laravel-ts-publish::enum',

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
    | Output TypeScript Definitions to Files
    |--------------------------------------------------------------------------
    |
    | Specifies whether to output the TypeScript definitions to files.
    */
    'output_to_files' => env('TS_PUBLISHER_OUTPUT_TO_FILES', true),

    'output_directory' => env('TS_PUBLISHER_OUTPUT_DIRECTORY', resource_path('/js/types/')),

    /*
    |--------------------------------------------------------------------------
    | Generate TypeScript Global Namespace
    |--------------------------------------------------------------------------
    |
    | Specifies whether to create a "global.d.ts" file with a global namespace containing all generated types.
    */
    'global' => env('TS_PUBLISHER_GLOBAL', true),

    'global_directory' => env('TS_PUBLISHER_GLOBAL_DIRECTORY', config('ts-publish.output_directory')),

    'global_filename' => env('TS_PUBLISHER_GLOBAL_FILENAME', 'laravel-ts-global.d.ts'),

    'models_namespace' => env('TS_PUBLISHER_MODELS_NAMESPACE', 'models'),

    'enums_namespace' => env('TS_PUBLISHER_ENUMS_NAMESPACE', 'enums'),

    'resources_namespace' => env('TS_PUBLISHER_RESOURCES_NAMESPACE', 'resources'),

    /*
    |--------------------------------------------------------------------------
    | Output the Results in a JSON File
    |--------------------------------------------------------------------------
    |
    | Specifies whether to output the generated TypeScript definitions in a JSON file.
    | This can be in addition to or instead of outputting to .d.ts files, depending on the "output_to_files" option.
    */
    'json' => env('TS_PUBLISHER_JSON', false),

    'json_filename' => env('TS_PUBLISHER_JSON_FILENAME', 'laravel-ts-definitions.json'),

    'json_output_directory' => env('TS_PUBLISHER_JSON_OUTPUT_DIRECTORY', config('ts-publish.output_directory')),

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
    | Model Finder Settings
    |--------------------------------------------------------------------------
    |
    | Below you can specify which models to include, exclude, or add additional directories to search for models in.
    | By default, the package will look for models in the app/Models directory and include all models found there.
    |
    | Settings can specific model class names or directories to search for models in. For example:
    | 'included_models' => [
    |     'App\Models\User', // Include only the User model
    |     'App\Models\Post', // Include only the Post model
    |     'vendor/<vendor_name>/src/Models', // Include all models in the vendor/<vendor_name>/src/Models directory
    | ],
    */

    'included_models' => [
        // Only these models are used
    ],

    'excluded_models' => [
        // These models are ignored
    ],

    'additional_model_directories' => [
        // Add additional directories to search for models in addition to the default app/Models directory
    ],

    /*
    |--------------------------------------------------------------------------
    | Enum Finder Settings
    |--------------------------------------------------------------------------
    |
    | Below you can specify which enums to include, exclude, or add additional directories to search for enums in.
    | By default, the package will look for enums in the app/Enums directory and include all enums found there.
    |
    | Settings can specific enum class names or directories to search for enums in. For example:
    | 'included_enums' => [
    |     'App\Enums\UserType', // Include only the UserType enum
    |     'App\Enums\PostStatus', // Include only the PostStatus enum
    |     'vendor/<vendor_name>/src/Enums', // Include all enums in the vendor/<vendor_name>/src/Enums directory
    | ],
    */

    'included_enums' => [
        // Only these enums are used
    ],

    'excluded_enums' => [
        // These enums are ignored
    ],

    'additional_enum_directories' => [
        // Add additional directories to search for enums in addition to the default app/Enums directory
    ],
];
