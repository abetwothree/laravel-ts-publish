<?php

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\User;
use Workbench\Shipping\Enums\Status as ShippingStatus;

use function Orchestra\Testbench\workbench_path;

beforeEach(function () {
    $this->service = new LaravelTsPublish;
});

describe('typesMap', function () {
    test('typesMap returns an array of type mappings', function () {
        $map = $this->service->typesMap();

        expect($map)
            ->toBeArray()
            ->toHaveKey('string')
            ->toHaveKey('integer')
            ->toHaveKey('boolean')
            ->toHaveKey('array')
            ->toHaveKey('json')
            ->toHaveKey('date');
    });
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

describe('phpToTypeScriptType', function () {
    test('phpToTypeScriptType resolves exact map matches', function () {
        $result = $this->service->phpToTypeScriptType('string');

        expect($result['type'])->toBe('string')
            ->and($result['enums'])->toBeEmpty()
            ->and($result['enumTypes'])->toBeEmpty()
            ->and($result['classes'])->toBeEmpty();
    });

    test('phpToTypeScriptType resolves integer to number', function () {
        expect($this->service->phpToTypeScriptType('integer')['type'])->toBe('number');
    });

    test('phpToTypeScriptType resolves boolean', function () {
        expect($this->service->phpToTypeScriptType('boolean')['type'])->toBe('boolean');
    });

    test('phpToTypeScriptType resolves class with TsType attribute', function () {
        $result = $this->service->phpToTypeScriptType(TsTypeAnnotatedCast::class);

        expect($result['type'])->toBe('CustomTsType')
            ->and($result['customImports'])->toBeEmpty();
    });

    test('phpToTypeScriptType resolves class with TsType array attribute including import', function () {
        $result = $this->service->phpToTypeScriptType(TsTypeAnnotatedCastWithImport::class);

        expect($result['type'])->toBe('ProductDimensions')
            ->and($result['customImports'])->toBe(['@js/types/product' => ['ProductDimensions']]);
    });

    test('phpToTypeScriptType resolves class with TsType array attribute without import', function () {
        $result = $this->service->phpToTypeScriptType(TsTypeAnnotatedCastWithoutImport::class);

        expect($result['type'])->toBe('InlineCustomType')
            ->and($result['customImports'])->toBeEmpty();
    });

    test('phpToTypeScriptType resolves enum class to Type alias', function () {
        $result = $this->service->phpToTypeScriptType(Status::class);

        expect($result['type'])->toBe('StatusType')
            ->and($result['enums'])->toBe(['Status'])
            ->and($result['enumTypes'])->toBe(['StatusType'])
            ->and($result['enumFqcns'])->toBe([Status::class]);
    });

    test('phpToTypeScriptType resolves unit enum class', function () {
        $result = $this->service->phpToTypeScriptType(Role::class);

        expect($result['type'])->toBe('RoleType')
            ->and($result['enums'])->toBe(['Role'])
            ->and($result['enumTypes'])->toBe(['RoleType'])
            ->and($result['enumFqcns'])->toBe([Role::class]);
    });

    test('phpToTypeScriptType resolves enum with TsEnum attribute to custom name', function () {
        $result = $this->service->phpToTypeScriptType(ShippingStatus::class);

        expect($result['type'])->toBe('ShipmentStatusType')
            ->and($result['enums'])->toBe(['ShipmentStatus'])
            ->and($result['enumTypes'])->toBe(['ShipmentStatusType']);
    });

    test('phpToTypeScriptType resolves enum without TsEnum to default name', function () {
        $result = $this->service->phpToTypeScriptType(Status::class);

        expect($result['type'])->toBe('StatusType')
            ->and($result['enums'])->toBe(['Status'])
            ->and($result['enumTypes'])->toBe(['StatusType']);
    });

    test('phpToTypeScriptType resolves CastsAttributes class via get return type', function () {
        $result = $this->service->phpToTypeScriptType(StringReturnCast::class);

        expect($result['type'])->toBe('string');
    });

    test('phpToTypeScriptType resolves CastsAttributes with unknown get return to unknown', function () {
        $result = $this->service->phpToTypeScriptType(UnknownReturnCast::class);

        expect($result['type'])->toBe('unknown');
    });

    test('phpToTypeScriptType resolves any other class to its basename', function () {
        $result = $this->service->phpToTypeScriptType(User::class);

        expect($result['type'])->toBe('User')
            ->and($result['classes'])->toBe(['User'])
            ->and($result['classFqcns'])->toBe([User::class]);
    });

    test('phpToTypeScriptType resolves Illuminate support collections to array or object shapes', function () {
        $result = $this->service->phpToTypeScriptType(Collection::class);

        expect($result['type'])->toBe('unknown[] | Record<string, unknown>')
            ->and($result['classes'])->toBeEmpty();
    });

    test('phpToTypeScriptType resolves Eloquent collections to arrays', function () {
        $result = $this->service->phpToTypeScriptType(Illuminate\Database\Eloquent\Collection::class);

        expect($result['type'])->toBe('Record<string, unknown>')
            ->and($result['classes'])->toBeEmpty();
    });

    test('phpToTypeScriptType resolves encrypted compound casts', function () {
        expect($this->service->phpToTypeScriptType('encrypted:array')['type'])->toBe('unknown[]');
    });

    test('phpToTypeScriptType resolves partial map matches', function () {
        // "varchar(255)" contains "varchar" → string
        expect($this->service->phpToTypeScriptType('varchar(255)')['type'])->toBe('string');
    });

    test('phpToTypeScriptType returns unknown for unresolvable types', function () {
        expect($this->service->phpToTypeScriptType('some_completely_fake_type')['type'])->toBe('unknown');
    });
});

describe('methodReturnedTypes', function () {
    test('methodReturnedTypes returns type info for an existing method', function () {
        $reflection = new ReflectionClass(Status::class);
        $result = $this->service->methodReturnedTypes($reflection, 'icon');

        expect($result['type'])->toBe('string');
    });

    test('methodReturnedTypes returns empty info for a missing method', function () {
        $reflection = new ReflectionClass(Status::class);
        $result = $this->service->methodReturnedTypes($reflection, 'nonExistentMethod');

        expect($result['type'])->toBe('unknown');
    });
});

describe('closureReturnedTypes', function () {
    test('closureReturnedTypes resolves a typed closure', function () {
        $closure = fn (): string => 'hello';

        expect($this->service->closureReturnedTypes($closure)['type'])->toBe('string');
    });

    test('closureReturnedTypes resolves a nullable closure', function () {
        $closure = fn (): ?int => null;

        expect($this->service->closureReturnedTypes($closure)['type'])->toBe('number | null');
    });

    test('closureReturnedTypes returns unknown for untyped closure', function () {
        $closure = fn () => 'hello';

        expect($this->service->closureReturnedTypes($closure)['type'])->toBe('unknown');
    });
});

describe('resolveReflectionType', function () {
    test('resolveReflectionType returns unknown for null type', function () {
        expect($this->service->resolveReflectionType(null)['type'])->toBe('unknown');
    });

    test('resolveReflectionType handles union types', function () {
        $closure = fn (): string|int => 'hello';
        $returnType = (new ReflectionFunction($closure))->getReturnType();

        $result = $this->service->resolveReflectionType($returnType);

        expect($result['type'])->toBe('string | number');
    });

    test('resolveReflectionType handles intersection types', function () {
        $closure = fn (): Countable&\Iterator => throw new RuntimeException('not called');
        $returnType = (new ReflectionFunction($closure))->getReturnType();

        $result = $this->service->resolveReflectionType($returnType);

        expect($result['type'])->toBe('unknown');
    });
});

describe('validJsObjectKey', function () {
    test('validJsObjectKey returns valid identifiers as-is', function () {
        expect($this->service->validJsObjectKey('myKey'))->toBe('myKey')
            ->and($this->service->validJsObjectKey('_private'))->toBe('_private')
            ->and($this->service->validJsObjectKey('$dollar'))->toBe('$dollar')
            ->and($this->service->validJsObjectKey('camelCase123'))->toBe('camelCase123');
    });

    test('validJsObjectKey quotes keys with special characters', function () {
        expect($this->service->validJsObjectKey('my-key'))->toBe('"my-key"')
            ->and($this->service->validJsObjectKey('has space'))->toBe('"has space"')
            ->and($this->service->validJsObjectKey('123start'))->toBe('"123start"');
    });
});

describe('toJsLiteral', function () {
    test('toJsLiteral converts null', function () {
        expect($this->service->toJsLiteral(null))->toBe('null');
    });

    test('toJsLiteral converts booleans', function () {
        expect($this->service->toJsLiteral(true))->toBe('true')
            ->and($this->service->toJsLiteral(false))->toBe('false');
    });

    test('toJsLiteral converts integers and floats', function () {
        expect($this->service->toJsLiteral(42))->toBe('42')
            ->and($this->service->toJsLiteral(3.14))->toBe('3.14');
    });

    test('toJsLiteral converts strings with proper escaping', function () {
        expect($this->service->toJsLiteral('hello'))->toBe("'hello'")
            ->and($this->service->toJsLiteral("it's"))->toBe("'it\\'s'")
            ->and($this->service->toJsLiteral("line\nnew"))->toBe("'line\\nnew'");
    });

    test('toJsLiteral converts BackedEnum to its value', function () {
        expect($this->service->toJsLiteral(Status::Draft))->toBe('0')
            ->and($this->service->toJsLiteral(Status::Published))->toBe('1');
    });

    test('toJsLiteral converts UnitEnum to its name', function () {
        expect($this->service->toJsLiteral(Role::Admin))->toBe("'Admin'")
            ->and($this->service->toJsLiteral(Role::Guest))->toBe("'Guest'");
    });

    test('toJsLiteral converts associative arrays to JS objects', function () {
        expect($this->service->toJsLiteral(['Draft' => 0, 'Published' => 1]))
            ->toBe('{Draft: 0, Published: 1}');
    });

    test('toJsLiteral converts list arrays to JS arrays', function () {
        expect($this->service->toJsLiteral([1, 2, 3]))->toBe('[1, 2, 3]');
    });

    test('toJsLiteral converts objects to JS objects', function () {
        $obj = (object) ['name' => 'test', 'value' => 42];

        expect($this->service->toJsLiteral($obj))->toBe("{name: 'test', value: 42}");
    });
});

describe('extractImportableTypes', function () {
    test('extractImportableTypes returns custom type names', function () {
        expect($this->service->extractImportableTypes('ProductMetadata'))
            ->toBe(['ProductMetadata']);
    });

    test('extractImportableTypes filters out primitives from union', function () {
        expect($this->service->extractImportableTypes('ProductMetadata | null'))
            ->toBe(['ProductMetadata']);
    });

    test('extractImportableTypes handles multiple custom types', function () {
        expect($this->service->extractImportableTypes('ProductMetadata | ProductJsonMetaData | null'))
            ->toBe(['ProductMetadata', 'ProductJsonMetaData']);
    });

    test('extractImportableTypes skips inline object types', function () {
        expect($this->service->extractImportableTypes('{ key: string } | null'))
            ->toBeEmpty();
    });

    test('extractImportableTypes skips tuple types', function () {
        expect($this->service->extractImportableTypes('[string, number] | null'))
            ->toBeEmpty();
    });

    test('extractImportableTypes skips generic types', function () {
        expect($this->service->extractImportableTypes('Array<string> | null'))
            ->toBeEmpty();
    });

    test('extractImportableTypes strips array shorthand', function () {
        expect($this->service->extractImportableTypes('MyType[]'))
            ->toBe(['MyType']);
    });

    test('extractImportableTypes deduplicates', function () {
        expect($this->service->extractImportableTypes('Foo | Foo | null'))
            ->toBe(['Foo']);
    });

    test('extractImportableTypes returns empty for all primitives', function () {
        expect($this->service->extractImportableTypes('string | number | boolean | null'))
            ->toBeEmpty();
    });
});

describe('resolveReflectionType with DNF union types', function () {
    test('resolveReflectionType handles DNF union with intersection member', function () {
        // (Countable&Iterator)|string is a DNF type — the intersection becomes 'unknown'
        $closure = fn (): (\Countable&\Iterator)|string => 'hello';
        $returnType = (new ReflectionFunction($closure))->getReturnType();

        $result = $this->service->resolveReflectionType($returnType);

        expect($result['type'])->toBe('unknown | string');
    });
});

describe('toJsLiteral unhandled types', function () {
    test('toJsLiteral returns null for unhandled types like resources', function () {
        $resource = fopen('php://memory', 'r');
        $result = $this->service->toJsLiteral($resource);
        fclose($resource);

        expect($result)->toBe('null');
    });
});

describe('TS_PRIMITIVES', function () {
    test('TS_PRIMITIVES contains all expected primitives', function () {
        expect(LaravelTsPublish::TS_PRIMITIVES)->toContain(
            'string', 'number', 'boolean', 'bigint', 'symbol',
            'null', 'undefined', 'object', 'unknown', 'any', 'never', 'void',
        );
    });
});

describe('emptyTypeScriptInfo', function () {
    test('emptyTypeScriptInfo returns the correct structure', function () {
        expect($this->service->emptyTypeScriptInfo())->toBe([
            'type' => 'unknown',
            'enums' => [],
            'enumTypes' => [],
            'classes' => [],
            'customImports' => [],
            'enumFqcns' => [],
            'classFqcns' => [],
        ]);
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
});

describe('sanitizeJsDoc', function () {
    test('escapes closing comment sequence', function () {
        expect($this->service->sanitizeJsDoc('some */ text'))->toBe('some *\/ text');
    });

    test('leaves normal text unchanged', function () {
        expect($this->service->sanitizeJsDoc('A normal description'))->toBe('A normal description');
    });

    test('handles multiple occurrences', function () {
        expect($this->service->sanitizeJsDoc('a */ b */ c'))->toBe('a *\/ b *\/ c');
    });

    test('handles empty string', function () {
        expect($this->service->sanitizeJsDoc(''))->toBe('');
    });
});

describe('parseDocBlockDescription', function () {
    test('returns empty string for false', function () {
        expect($this->service->parseDocBlockDescription(false))->toBe('');
    });

    test('returns empty string for empty string', function () {
        expect($this->service->parseDocBlockDescription(''))->toBe('');
    });

    test('extracts description from single-line doc block', function () {
        $doc = '/** A simple description */';
        expect($this->service->parseDocBlockDescription($doc))->toBe('A simple description');
    });

    test('extracts description from multi-line doc block', function () {
        $doc = <<<'DOC'
/**
 * First line of description.
 * Second line of description.
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('First line of description. Second line of description.');
    });

    test('filters out @-tag lines', function () {
        $doc = <<<'DOC'
/**
 * The actual description.
 *
 * @param string $name
 * @return void
 * @phpstan-type Foo = array{bar: string}
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('The actual description.');
    });

    test('returns empty string when doc block has only tags', function () {
        $doc = <<<'DOC'
/**
 * @param string $name
 * @return void
 */
DOC;
        expect($this->service->parseDocBlockDescription($doc))->toBe('');
    });
});

describe('callCommandUsing and callCommandWith', function () {
    afterEach(function () {
        // Reset static state to prevent leaking across tests
        $prop = (new ReflectionClass(LaravelTsPublish::class))->getProperty('callCommandWith');
        $prop->setValue(null, null);
    });

    test('callCommandWith does nothing when no closure is registered', function () {
        // Should not throw — just a no-op
        $this->service->callCommandWith();

        expect(true)->toBeTrue();
    });

    test('callCommandUsing registers a closure that callCommandWith executes', function () {
        $called = false;

        LaravelTsPublish::callCommandUsing(function () use (&$called) {
            $called = true;
        });

        expect($called)->toBeFalse();

        $this->service->callCommandWith();

        expect($called)->toBeTrue();
    });

    test('callCommandWith can modify config values', function () {
        LaravelTsPublish::callCommandUsing(function () {
            config()->set('ts-publish.additional_model_directories', ['modules/Blog/Models']);
        });

        expect(config('ts-publish.additional_model_directories'))->not->toBe(['modules/Blog/Models']);

        $this->service->callCommandWith();

        expect(config('ts-publish.additional_model_directories'))->toBe(['modules/Blog/Models']);
    });

    test('later callCommandUsing replaces the previous closure', function () {
        $firstCalled = false;
        $secondCalled = false;

        LaravelTsPublish::callCommandUsing(function () use (&$firstCalled) {
            $firstCalled = true;
        });

        LaravelTsPublish::callCommandUsing(function () use (&$secondCalled) {
            $secondCalled = true;
        });

        $this->service->callCommandWith();

        expect($firstCalled)->toBeFalse()
            ->and($secondCalled)->toBeTrue();
    });

    test('callCommandWith only runs the closure once per invocation', function () {
        $count = 0;

        LaravelTsPublish::callCommandUsing(function () use (&$count) {
            $count++;
        });

        $this->service->callCommandWith();
        $this->service->callCommandWith();

        expect($count)->toBe(2);
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
});

/**
 * A class annotated with #[TsType] for testing step 2 resolution.
 */
#[TsType('CustomTsType')]
class TsTypeAnnotatedCast {}

/**
 * A class annotated with #[TsType] using an array with type and import for testing step 2 resolution.
 */
#[TsType(['type' => 'ProductDimensions', 'import' => '@js/types/product'])]
class TsTypeAnnotatedCastWithImport {}

/**
 * A class annotated with #[TsType] using an array with only type (no import) for testing step 2 resolution.
 */
#[TsType(['type' => 'InlineCustomType'])]
class TsTypeAnnotatedCastWithoutImport {}

/**
 * A CastsAttributes class whose get() returns string, for testing step 4.
 *
 * @implements CastsAttributes<string, string>
 */
class StringReturnCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) $value;
    }
}

/**
 * A CastsAttributes class whose get() has no return type, for testing step 4 fallback.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class UnknownReturnCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes) // @phpstan-ignore missingType.return
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes) // @phpstan-ignore missingType.return
    {
        return $value;
    }
}
