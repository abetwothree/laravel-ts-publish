# Recommended Installation & Workflow

This is a recommended workflow for using this package, but feel free to adapt it to your needs.

## Installation & Setup

### Install this PHP package via composer

```bash
composer require abetwothree/laravel-ts-publish
```

### Install tolki/enum Package
 
(Optional but recommended) Install the tolki/enum package to use the helper functions, types, and Vite plugin for automatic publishing on file changes or on production build.

```bash
npm install @tolki/enum --save
```

If you don't use the `@tolki/enum` package, make sure to set the `enums_use_tolki_package` config setting to `false` in the published configuration file.

### Recommended directory structure and configuration

By default, the directory where the files are published is `resources/js/types/data`. It's recommended to gitignore the generated files to avoid committing a large number of generated files where the types are not typically used in production builds of a frontend app and the enum files can be easily regenerated on the server or CI pipeline before building assets for production. This also keeps your version control history cleaner and avoids merge conflicts in generated files.

E.g.:

```php
// config/ts-publish.php

'output_directory' => resource_path('/js/types/data'),
```

```gitignore
# Ignore published TypeScript files
/resources/js/types/data/
```

If you use ESLint or Oxlint, it is recommended to add the published directory to the ignore list in your linter config as well.

### Importing the published files

Create import aliases for the published files in `tsconfig.json` & `vite.config.ts` to avoid long relative paths in your code and to make it clear that these are generated files.

```json
{
  "compilerOptions": {
    "baseUrl": ".",
    "paths": {
      "@data/*": ["resources/js/types/data/*"]
    }
  }
}
```

```typescript
import { defineConfig } from "vite";
import path from 'path';

export default defineConfig({
  resolve: {
        alias: {
            '@data': path.resolve(__dirname, 'resources/js/types/data'),
        },
    },
});
```

Then import your types or enums like this:

```typescript
import { Status } from '@data/enums';
import { User } from '@data/models';
```

If you use the module publishing feature, you can also import the generated module files:

```typescript
import { Status } from '@data/app/enums';
import { User } from '@data/app/models';
```

### Add the Vite plugin for automatic publishing

(Optional) If you installed the `@tolki/enum` package, add the Vite plugin to automatically watch for changes in the transformed PHP files and call the publish command.

Be sure to read the [plugin documentation](https://tolki.abe.dev/enums/enum-vite-plugin.html) for the full details and configuration options.

```javascript
import { defineConfig } from 'vite';
import { laravelTsPublish } from "@tolki/enum/vite";

export default defineConfig({
  plugins: [laravelTsPublish()],
});
```

If you use docker (sail) for local development it may be worth to configure the command on your Vite config using an `.env` variable. Example:

```text
# .env - local
VITE_TS_PUBLISH="./vendor/bin/sail artisan ts:publish"
```

```javascript
import { defineConfig, loadEnv } from "vite";
import { laravelTsPublish } from "@tolki/enum/vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravelTsPublish({
                command: env.VITE_TS_PUBLISH,
            }),
        ],
        resolve: {
            alias: {
                '@data': path.resolve(__dirname, 'resources/js/types/data'),
            },
        },
    };
});
```

Then your cloud environment or CI pipelines it will default to the standard `php artisan ts:publish` command, but in your local environment it will use the sail command to run the publish command inside the container.

Alternatively, in your cloud or CI environments you can set the `VITE_TS_PUBLISH` variable to whatever configuration of the `ts:publish` command you need to publish files in those environments.

### Add the publish command to composer post update hook

Add the `ts:publish` command to the post update command hook in `composer.json` to automatically republish files on composer updates.

```json
{
  "scripts": {
    "post-update-cmd": [
      "@php artisan ts:publish"
    ]
  }
}
```

### Optionally, add the pre-command hook to your AppServiceProvider

If you want to perform any additional configuration or setup before the publish command runs, you can use the `callCommandUsing` method in the `boot` method of your `AppServiceProvider` or any other service provider. See more info about this in the [Pre-Command Hook](https://github.com/abetwothree/laravel-ts-publish#pre-command-hook) section of the documentation.

```php
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        LaravelTsPublish::callCommandUsing(function () {
            // configure anything needed before the `ts:publish` command runs
        });
    }
}
```

## Development Workflow

During development, you can run `vite dev` and the plugin will automatically watch for changes in the transformed PHP files and call the publish command to keep your TypeScript files up to date.

You can also run `vite build` to build your assets for production, and the plugin will call the publish command before bundling.
