<?php

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;

return [
    'enabled' => env('TS_PUBLISHER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Run After Migrate
    |--------------------------------------------------------------------------
    |
    | Specifies whether to recreate TypeScript declaration files after running migrations.
    |
    | Ignore if not outputting to files
    */
    'run-after-migrate' => env('TS_PUBLISHER_RUN_AFTER_MIGRATE', false),

    /*
    |--------------------------------------------------------------------------
    | File Generator Classes
    |--------------------------------------------------------------------------
    |
    | Specifies the classes responsible for generating the TypeScript definitions.
    | You can extend the default generators or create your own.
    */
    'model-generator-class' => ModelGenerator::class,

    'enum-generator-class' => EnumGenerator::class,

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
    'output-to-files' => env('TS_PUBLISHER_OUTPUT_TO_FILES', true),

    'output-directory' => env('TS_PUBLISHER_OUTPUT_DIRECTORY', resource_path('/js/types/')),

    /*
    |--------------------------------------------------------------------------
    | Generate TypeScript Global Namespace
    |--------------------------------------------------------------------------
    |
    | Specifies whether to create a "global.d.ts" file with a global namespace containing all generated types.
    */
    'global' => env('TS_PUBLISHER_GLOBAL', true),

    'global-directory' => env('TS_PUBLISHER_GLOBAL_DIRECTORY', config('ts-publish.output-directory')),

    'global-filename' => env('TS_PUBLISHER_GLOBAL_FILENAME', 'laravel-ts-global.d.ts'),

    'models-namespace' => env('TS_PUBLISHER_MODELS_NAMESPACE', 'models'),

    'enums-namespace' => env('TS_PUBLISHER_ENUMS_NAMESPACE', 'enums'),

    'resources-namespace' => env('TS_PUBLISHER_RESOURCES_NAMESPACE', 'resources'),

    /*
    |--------------------------------------------------------------------------
    | Output the Results in a JSON File
    |--------------------------------------------------------------------------
    |
    | Specifies whether to output the generated TypeScript definitions in a JSON file.
    | This can be in addition to or instead of outputting to .d.ts files, depending on the "output-to-files" option.
    */
    'json' => env('TS_PUBLISHER_JSON', false),

    'json-filename' => env('TS_PUBLISHER_JSON_FILENAME', 'laravel-ts-definitions.json'),

    'json-output-directory' => env('TS_PUBLISHER_JSON_OUTPUT_DIRECTORY', config('ts-publish.output-directory')),

    /*
    |--------------------------------------------------------------------------
    | Map Timestamps as Date Object Types
    |--------------------------------------------------------------------------
    |
    | Specifies whether to map timestamp fields as Date objects in the
    | generated TypeScript definitions instead of strings.
    */
    'timestamps-as-date' => false,

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
