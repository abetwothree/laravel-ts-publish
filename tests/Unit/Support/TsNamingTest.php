<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TsNaming;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\MerchantResource;
use Workbench\App\Http\Resources\PostResource;

beforeEach(function () {
    $this->service = new TsNaming;
});

describe('keyCase', function () {
    test('keyCase returns camelCase', function () {
        expect($this->service->keyCase('some_relation', 'camel'))->toBe('someRelation');
    });

    test('keyCase returns snake_case', function () {
        expect($this->service->keyCase('someRelation', 'snake'))->toBe('some_relation');
    });

    test('keyCase returns PascalCase', function () {
        expect($this->service->keyCase('some_relation', 'pascal'))->toBe('SomeRelation');
    });

    test('keyCase returns the original key by default', function () {
        expect($this->service->keyCase('some_relation', 'none'))->toBe('some_relation');
    });
});

describe('namespaceToPath', function () {
    test('converts simple FQCN to kebab path', function () {
        expect($this->service->namespaceToPath('App\Models\User'))->toBe('app/models');
    });

    test('converts module FQCN to kebab path', function () {
        expect($this->service->namespaceToPath('Blog\Enums\ArticleStatus'))->toBe('blog/enums');
    });

    test('handles multi-word segments with kebab case', function () {
        expect($this->service->namespaceToPath('App\UserSettings\AccountPreference'))->toBe('app/user-settings');
    });

    test('strips configured namespace prefix', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Modules\\');

        expect($this->service->namespaceToPath('Modules\Blog\Enums\ArticleStatus'))->toBe('blog/enums');
    });

    test('strips Workbench prefix for testing', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        expect($this->service->namespaceToPath('Workbench\App\Models\User'))->toBe('app/models')
            ->and($this->service->namespaceToPath('Workbench\Blog\Enums\ArticleStatus'))->toBe('blog/enums');
    });

    test('does not strip prefix when prefix does not match', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Modules\\');

        expect($this->service->namespaceToPath('App\Models\User'))->toBe('app/models');
    });

    test('handles deeply nested namespaces', function () {
        expect($this->service->namespaceToPath('App\Domain\Billing\Models\Invoice'))->toBe('app/domain/billing/models');
    });
});

describe('relativeImportPath', function () {
    test('same directory returns dot', function () {
        expect($this->service->relativeImportPath('blog/models', 'blog/models'))->toBe('.');
    });

    test('sibling directory computes one level up', function () {
        expect($this->service->relativeImportPath('blog/models', 'blog/enums'))->toBe('../enums');
    });

    test('cross-module computes multiple levels up', function () {
        expect($this->service->relativeImportPath('app/models', 'blog/enums'))->toBe('../../blog/enums');
    });

    test('child to parent directory', function () {
        expect($this->service->relativeImportPath('app/domain/billing/models', 'app/domain/billing/enums'))->toBe('../enums');
    });

    test('deeply nested cross-module', function () {
        expect($this->service->relativeImportPath('app/domain/billing/models', 'shipping/enums'))->toBe('../../../../shipping/enums');
    });

    test('going up to common root', function () {
        expect($this->service->relativeImportPath('app/models', 'app/enums'))->toBe('../enums');
    });

    test('same-directory child target is prefixed with ./', function () {
        expect($this->service->relativeImportPath('models', 'models/videos'))->toBe('./videos');
    });

    test('descendant target several levels deep is prefixed with ./', function () {
        expect($this->service->relativeImportPath('models', 'models/foo/bar'))->toBe('./foo/bar');
    });

    test('descendant target under a prefixed root is prefixed with ./', function () {
        expect($this->service->relativeImportPath('app/models', 'app/models/videos'))->toBe('./videos');
    });
});

describe('sortImportPaths', function () {
    test('packages come before relative imports', function () {
        $imports = [
            '../enums' => ['Status'],
            'luxon' => ['DateTime'],
        ];

        $sorted = $this->service->sortImportPaths($imports);

        expect(array_keys($sorted))->toBe(['luxon', '../enums']);
    });

    test('deeper relative imports come before shallower ones', function () {
        $imports = [
            './types' => ['UserType'],
            '../../shared/enums' => ['Status'],
            '../enums' => ['Role'],
        ];

        $sorted = $this->service->sortImportPaths($imports);

        expect(array_keys($sorted))->toBe(['../../shared/enums', '../enums', './types']);
    });

    test('bare parent path (..) sorts by depth with other single-level relative paths', function () {
        $imports = [
            '.' => ['MerchandiseCategory'],
            '..' => ['Permission'],
            '../favorites' => ['Favorite'],
            '../images' => ['Image'],
            '../../enums' => ['StatusType'],
            '../../../owen-it/auditing/models' => ['Audit'],
        ];

        $sorted = $this->service->sortImportPaths($imports);

        expect(array_keys($sorted))->toBe([
            '../../../owen-it/auditing/models',
            '../../enums',
            '..',
            '../favorites',
            '../images',
            '.',
        ]);
    });

    test('alphabetical within the same group', function () {
        $imports = [
            'zod' => ['z'],
            'axios' => ['AxiosInstance'],
            'luxon' => ['DateTime'],
        ];

        $sorted = $this->service->sortImportPaths($imports);

        expect(array_keys($sorted))->toBe(['axios', 'luxon', 'zod']);
    });

    test('full sort order: packages then relative by depth then alpha', function () {
        $imports = [
            '.' => ['MerchandiseCategory'],
            './types' => ['PostType'],
            '@tanstack/query' => ['useQuery'],
            '..' => ['Permission'],
            '../enums' => ['Status'],
            'luxon' => ['DateTime'],
            '../../shared/enums' => ['Role'],
        ];

        $sorted = $this->service->sortImportPaths($imports);

        expect(array_keys($sorted))->toBe([
            '@tanstack/query',
            'luxon',
            '../../shared/enums',
            '..',
            '../enums',
            '.',
            './types',
        ]);
    });

    test('preserves values when sorting', function () {
        $imports = [
            '../enums' => ['Status', 'Role'],
            'luxon' => ['DateTime'],
        ];

        $sorted = $this->service->sortImportPaths($imports);

        expect($sorted['luxon'])->toBe(['DateTime'])
            ->and($sorted['../enums'])->toBe(['Status', 'Role']);
    });

    test('empty array returns empty array', function () {
        expect($this->service->sortImportPaths([]))->toBe([]);
    });

    test('non-package non-relative paths sort between packages and relative imports', function () {
        $imports = [
            '../enums' => ['Status'],
            '~special/utils' => ['Helper'],
            'luxon' => ['DateTime'],
        ];

        $sorted = $this->service->sortImportPaths($imports);

        expect(array_keys($sorted))->toBe(['luxon', '~special/utils', '../enums']);
    });
});

describe('resolveClassFromFile', function () {
    test('resolves FQCN from an enum file', function () {
        $filePath = workbench_path('app/Enums/Status.php');
        $result = $this->service->resolveClassFromFile($filePath);

        expect($result)->toBe('Workbench\App\Enums\Status');
    });

    test('resolves FQCN from a model file', function () {
        $filePath = workbench_path('app/Models/User.php');
        $result = $this->service->resolveClassFromFile($filePath);

        expect($result)->toBe('Workbench\App\Models\User');
    });

    test('returns null for a file without a class', function () {
        $filePath = workbench_path('routes/web.php');
        $result = $this->service->resolveClassFromFile($filePath);

        expect($result)->toBeNull();
    });

    test('returns null for a non-existent file', function () {
        $result = $this->service->resolveClassFromFile('/non/existent/file.php');

        expect($result)->toBeNull();
    });

    test('resolves class from relative file path via base_path', function () {
        // Pass a relative path (no leading /) so base_path() is invoked
        $result = $this->service->resolveClassFromFile('some/nonexistent/file.php');

        expect($result)->toBeNull();
    });
});

describe('resourceTypeName', function () {
    test('falls back to the class basename', function () {
        expect($this->service->resourceTypeName(PostResource::class))->toBe('PostResource');
    });

    test('falls back to the class basename when #[TsResource] carries no name', function () {
        expect($this->service->resourceTypeName(MerchantResource::class))->toBe('MerchantResource');
    });

    test('#[TsResource(name:)] renames the emitted interface', function () {
        expect($this->service->resourceTypeName(AddressResource::class))->toBe('Address');
    });

    test('a repeated lookup returns the cached name', function () {
        expect($this->service->resourceTypeName(AddressResource::class))->toBe('Address')
            ->and($this->service->resourceTypeName(AddressResource::class))->toBe('Address');
    });

    test('a class that does not exist falls back to its basename', function () {
        expect($this->service->resourceTypeName('App\\Http\\Resources\\NopeResource'))->toBe('NopeResource');
    });
});
