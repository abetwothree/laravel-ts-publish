<?php

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\Shipping\Enums\Status as ShippingStatus;

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
        config()->set('ts-publish.relationship_case', 'camel');

        expect($this->service->keyCase('some_relation'))->toBe('someRelation');
    });

    test('keyCase returns snake_case', function () {
        config()->set('ts-publish.relationship_case', 'snake');

        expect($this->service->keyCase('someRelation'))->toBe('some_relation');
    });

    test('keyCase returns PascalCase', function () {
        config()->set('ts-publish.relationship_case', 'pascal');

        expect($this->service->keyCase('some_relation'))->toBe('SomeRelation');
    });

    test('keyCase returns the original key by default', function () {
        config()->set('ts-publish.relationship_case', 'none');

        expect($this->service->keyCase('some_relation'))->toBe('some_relation');
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

        expect($result['type'])->toBe('CustomTsType');
    });

    test('phpToTypeScriptType resolves enum class to Type alias', function () {
        $result = $this->service->phpToTypeScriptType(Status::class);

        expect($result['type'])->toBe('StatusType')
            ->and($result['enums'])->toBe(['Status'])
            ->and($result['enumTypes'])->toBe(['StatusType']);
    });

    test('phpToTypeScriptType resolves unit enum class', function () {
        $result = $this->service->phpToTypeScriptType(Role::class);

        expect($result['type'])->toBe('RoleType')
            ->and($result['enums'])->toBe(['Role'])
            ->and($result['enumTypes'])->toBe(['RoleType']);
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
        $result = $this->service->phpToTypeScriptType(\Workbench\App\Models\User::class);

        expect($result['type'])->toBe('User')
            ->and($result['classes'])->toBe(['User']);
    });

    test('phpToTypeScriptType resolves encrypted compound casts', function () {
        expect($this->service->phpToTypeScriptType('encrypted:array')['type'])->toBe('Array<unknown>');
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
        $closure = fn (): \Countable&\Iterator => throw new \RuntimeException('not called');
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
        ]);
    });
});

/**
 * A class annotated with #[TsType] for testing step 2 resolution.
 */
#[TsType('CustomTsType')]
class TsTypeAnnotatedCast {}

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
