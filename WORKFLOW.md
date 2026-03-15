# Recommend Installation & Workflow

This is a recommended workflow for using this package, but feel free to adapt it to your needs.

## Installation & Setup

### Install the this PHP package via composer

```bash
composer require abetwothree/laravel-ts-publish
```

### Install tolki/enum Package
 
(Optional but recommended) Install the tolki/enum package to use the helper functions, types, and Vite plugin for automatic publishing on file changes or on production build.

```bash
npm install @tolki/enum
```

If you don't use the `@tolki/enum` package, make sure to set the `enums_use_tolki_package` config setting to `false` in the published configuration file.

### Recommended directory structure and configuration

Update the directory where the files are published and then gitignore the generated files. This avoids committing a large number of generated files that are not typically used in production builds of a frontend app.

The Vite plugin will trigger the publishing of enums before building assets on CI pipelines or servers, so the published files will still be available for production builds without being committed to version control.

E.g.:

```php
// config/ts-publish.php

'output_directory' => resource_path('/js/types/data'),
```

```gitignore
# Ignore published TypeScript files
/resources/js/types/data/
```

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
```

### Add the Vite plugin for automatic publishing

(Optional) If you installed the `@tolki/enum` package, add the Vite plugin to automatically watch for changes in the transformed PHP files and call the publish command.

Be sure to read the [plugin documentation](https://tolki.abe.dev/enums/enum-vite-plugin.html) for the full details and configuration options.

```javascript
import { defineConfig } from "vite";
import { laravelTsPublish } from "@tolki/enum/vite";

export default defineConfig({
  plugins: [laravelTsPublish()],
});
```

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

## Development Workflow

During development, you can run `vite dev` and the plugin will automatically watch for changes in the transformed PHP files and call the publish command to keep your TypeScript files up to date.

You can also run `vite build` to build your assets for production, and the plugin will call the publish command before bundling.
