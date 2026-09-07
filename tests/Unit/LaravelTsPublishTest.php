<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Casts\MenuSettings;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\ArrayableData;
use Workbench\App\ValueObjects\CapabilitiesDto;
use Workbench\App\ValueObjects\Coordinate;
use Workbench\App\ValueObjects\GridConfigDto;
use Workbench\App\ValueObjects\Money;
use Workbench\App\ValueObjects\OpaqueHandle;
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

describe('relationsMap', function () {
    test('relationsMap returns an array of relation mappings', function () {
        $map = $this->service->relationsMap();

        expect($map)
            ->toBeArray()
            ->toHaveKey(HasOne::class)
            ->toHaveKey(MorphOne::class)
            ->toHaveKey(HasOneThrough::class)
            ->toHaveKey(BelongsTo::class)
            ->toHaveKey(MorphTo::class)
            ->toHaveKey(HasMany::class)
            ->toHaveKey(HasManyThrough::class)
            ->toHaveKey(BelongsToMany::class)
            ->toHaveKey(MorphMany::class)
            ->toHaveKey(MorphToMany::class);
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

describe('toTsType', function () {
    test('toTsType resolves exact map matches', function () {
        $result = $this->service->toTsType('string');

        expect($result['type'])->toBe('string')
            ->and($result['enums'])->toBeEmpty()
            ->and($result['enumTypes'])->toBeEmpty()
            ->and($result['classes'])->toBeEmpty();
    });

    test('toTsType resolves integer to number', function () {
        expect($this->service->toTsType('integer')['type'])->toBe('number');
    });

    test('toTsType resolves boolean', function () {
        expect($this->service->toTsType('boolean')['type'])->toBe('boolean');
    });

    test('toTsType resolves class with TsType attribute', function () {
        $result = $this->service->toTsType(TsTypeAnnotatedCast::class);

        expect($result['type'])->toBe('CustomTsType')
            ->and($result['customImports'])->toBeEmpty();
    });

    test('toTsType resolves class with TsType array attribute including import', function () {
        $result = $this->service->toTsType(TsTypeAnnotatedCastWithImport::class);

        expect($result['type'])->toBe('ProductDimensions')
            ->and($result['customImports'])->toBe(['@js/types/product' => ['ProductDimensions']]);
    });

    test('toTsType resolves class with TsType array attribute without import', function () {
        $result = $this->service->toTsType(TsTypeAnnotatedCastWithoutImport::class);

        expect($result['type'])->toBe('InlineCustomType')
            ->and($result['customImports'])->toBeEmpty();
    });

    test('toTsType resolves enum class to Type alias', function () {
        $result = $this->service->toTsType(Status::class);

        expect($result['type'])->toBe('StatusType')
            ->and($result['enums'])->toBe(['Status'])
            ->and($result['enumTypes'])->toBe(['StatusType'])
            ->and($result['enumFqcns'])->toBe([Status::class]);
    });

    test('toTsType resolves unit enum class', function () {
        $result = $this->service->toTsType(Role::class);

        expect($result['type'])->toBe('RoleType')
            ->and($result['enums'])->toBe(['Role'])
            ->and($result['enumTypes'])->toBe(['RoleType'])
            ->and($result['enumFqcns'])->toBe([Role::class]);
    });

    test('toTsType resolves enum with TsEnum attribute to custom name', function () {
        $result = $this->service->toTsType(ShippingStatus::class);

        expect($result['type'])->toBe('ShipmentStatusType')
            ->and($result['enums'])->toBe(['ShipmentStatus'])
            ->and($result['enumTypes'])->toBe(['ShipmentStatusType']);
    });

    test('toTsType resolves enum without TsEnum to default name', function () {
        $result = $this->service->toTsType(Status::class);

        expect($result['type'])->toBe('StatusType')
            ->and($result['enums'])->toBe(['Status'])
            ->and($result['enumTypes'])->toBe(['StatusType']);
    });

    test('toTsType resolves CastsAttributes class via get return type', function () {
        $result = $this->service->toTsType(StringReturnCast::class);

        expect($result['type'])->toBe('string');
    });

    test('toTsType resolves CastsAttributes with unknown get return to unknown', function () {
        $result = $this->service->toTsType(UnknownReturnCast::class);

        expect($result['type'])->toBe('unknown');
    });

    test('toTsType resolves any other class to its basename', function () {
        $result = $this->service->toTsType(User::class);

        expect($result['type'])->toBe('User')
            ->and($result['classes'])->toBe(['User'])
            ->and($result['classFqcns'])->toBe([User::class]);
    });

    test('toTsType resolves Illuminate support collections to array or object shapes', function () {
        $result = $this->service->toTsType(Collection::class);

        expect($result['type'])->toBe('unknown[] | Record<string, unknown>')
            ->and($result['classes'])->toBeEmpty();
    });

    test('toTsType resolves Eloquent collections to arrays', function () {
        $result = $this->service->toTsType(Illuminate\Database\Eloquent\Collection::class);

        expect($result['type'])->toBe('Record<string, unknown>')
            ->and($result['classes'])->toBeEmpty();
    });

    test('toTsType resolves encrypted compound casts', function () {
        expect($this->service->toTsType('encrypted:array')['type'])->toBe('unknown[]');
    });

    test('toTsType resolves partial map matches', function () {
        expect($this->service->toTsType('varchar(255)')['type'])->toBe('string');
    });

    test('toTsType returns unknown for unresolvable types', function () {
        expect($this->service->toTsType('some_completely_fake_type')['type'])->toBe('unknown');
    });

    test('toTsType resolves ?string to string | null', function () {
        $result = $this->service->toTsType('?string');

        expect($result['type'])->toBe('string | null');
    });

    test('toTsType resolves ?int to number | null', function () {
        $result = $this->service->toTsType('?int');

        expect($result['type'])->toBe('number | null');
    });

    test('toTsType resolves ?Status enum to StatusType | null', function () {
        $result = $this->service->toTsType('?'.Status::class);

        expect($result['type'])->toBe('StatusType | null')
            ->and($result['enumFqcns'])->toContain(Status::class);
    });

    test('toTsType resolves Arrayable class to unknown[]', function () {
        $result = $this->service->toTsType(ArrayableValueObject::class);

        expect($result['type'])->toBe('unknown[]')
            ->and($result['classes'])->toBeEmpty()
            ->and($result['classFqcns'])->toBeEmpty();
    });

    test('toTsType resolves class with __toString to string', function () {
        $result = $this->service->toTsType(StringableValueObject::class);

        expect($result['type'])->toBe('string')
            ->and($result['classes'])->toBeEmpty()
            ->and($result['classFqcns'])->toBeEmpty();
    });

    test('toTsType resolves numeric-string to string via exact map', function () {
        expect($this->service->toTsType('numeric-string')['type'])->toBe('string');
    });

    test('toTsType resolves positive-int to number via partial map match', function () {
        expect($this->service->toTsType('positive-int')['type'])->toBe('number');
    });

    test('toTsType resolves array-key to string | number', function () {
        expect($this->service->toTsType('array-key')['type'])->toBe('string | number');
    });

    test('toTsType resolves scalar to string | number | boolean', function () {
        expect($this->service->toTsType('scalar')['type'])->toBe('string | number | boolean');
    });

    test('toTsType resolves never to never', function () {
        expect($this->service->toTsType('never')['type'])->toBe('never');
    });

    test('toTsType resolves void to void', function () {
        expect($this->service->toTsType('void')['type'])->toBe('void');
    });
});

describe('castable-with-arguments cast strings', function () {
    test('AsEnumCollection:Enum resolves to an enum array with FQCNs plumbed', function () {
        $info = $this->service->toTsType(
            AsEnumCollection::class.':'.Status::class,
        );

        expect($info['type'])->toBe('StatusType[]')
            ->and($info['enumFqcns'])->toBe([Status::class]);
    });

    test('AsCollection:,MappedClass resolves the element shape', function () {
        $info = $this->service->toTsType(
            AsCollection::class.':,'.GridConfigDto::class,
        );

        expect($info['type'])->toBe('{ label: string; config: Record<string, unknown> }[]');
    });

    test('AsCollection with no map still resolves to unknown[]', function () {
        expect($this->service->toTsType(AsCollection::class.':,')['type'])
            ->toBe('unknown[]')
            ->and($this->service->toTsType(AsCollection::class)['type'])
            ->toBe('unknown[]');
    });

    test('a custom CastsAttributes cast with args resolves through the cast class', function () {
        expect($this->service->toTsType(MenuSettings::class.':whatever')['type'])
            ->toBe($this->service->toTsType(MenuSettings::class)['type']);
    });

    test('an unresolvable AsEnumCollection argument degrades via the exact map, not a crash', function () {
        expect($this->service->toTsType(AsEnumCollection::class.':NotARealEnum')['type'])
            ->toBe('unknown[]');
    });

    test('AsEncryptedCollection with arguments degrades gracefully via the final branch', function () {
        // AsEncryptedCollection does not extend AsCollection (verified against vendor source), so it
        // falls through resolveCastWithArguments()'s final branch — the bare-class exact map match.
        expect($this->service->toTsType(AsEncryptedCollection::class.':,'.GridConfigDto::class)['type'])
            ->toBe('unknown[]');
    });

    test('a DB cast string with a class-less head is untouched by the colon-split branch', function () {
        expect($this->service->toTsType('decimal:2')['type'])->toBe('number');
    });
});

describe('toTsType substring fallback restriction', function () {
    test('class-ish names degrade to unknown instead of partial-matching', function (string $name) {
        expect($this->service->toTsType($name)['type'])->toBe('unknown');
    })->with([
        'Point', 'Constraint', 'Blueprint', 'Endpoint', 'Waypoint', 'Realm',
        'Print', 'Integration', 'Maintenance', 'Interface',
        'Update', 'Candidate', 'Runtime', 'Chart',
        'DateTimeInterface', 'App\\Casts\\NotARealCast', '\\Foo\\Bar',
    ]);

    test('a class name that case-insensitively equals a literal DB type keyword is caught earlier, at the exact-match step, not here', function () {
        // 'Character' lowercases to 'character', a literal typesMap key, so step 1's exact match beats step 7's gate.
        expect($this->service->toTsType('Character')['type'])->toBe('string');
    });

    test('a leading delimiter must not defeat the class-ish gate', function (string $name) {
        // A leading '(', ':' or space makes looksLikeClassName()'s head empty; PREG_SPLIT_NO_EMPTY drops it.
        expect($this->service->toTsType($name)['type'])->toBe('unknown');
    })->with([
        ' Point', ':Point', '(Point)',
    ]);

    test('real DB and cast type strings still resolve through the fallback', function (string $input, string $expected) {
        expect($this->service->toTsType($input)['type'])->toBe($expected);
    })->with([
        // 'tinyint(1)' is Laravel's boolean() column on MySQL/SQLite (step 1's exact-match now
        // catches the literal parameterized key before the 'int' substring can).
        ['tinyint(1)', 'boolean'],
        ['tinyint(4)', 'number'],
        ['varchar(255)', 'string'],
        ['numeric(10,2)', 'number'],
        ['decimal:2', 'number'],
        ['date:Y-m-d', 'string'],
        ['datetime:Y-m-d H:i:s', 'string'],
        ['immutable_datetime:Y-m-d', 'string'],
        ['timestamp without time zone', 'string'],
        ['character varying(255)', 'string'],
        ['int unsigned', 'number'],
        ['bigint unsigned', 'number'],
        ['double precision', 'number'],
    ]);
});

describe('toTsType bare-name fallback for sized native types', function () {
    test('a sized type with no exact entry resolves via the name before the paren', function (string $input, string $expected) {
        expect($this->service->toTsType($input)['type'])->toBe($expected);
    })->with([
        ['binary(255)', 'string'],
        ['varbinary(255)', 'string'],
        ['nvarchar(max)', 'string'],
        ["set('a','b')", 'string'],
        ['vector(1536)', 'number[]'],
    ]);

    test('a bare tinyint with no width is a genuine small integer', function () {
        expect($this->service->toTsType('tinyint')['type'])->toBe('number');
    });

});

describe('toTsType Step 3 judgment calls: vector, geometry/geography, set', function () {
    test('vector resolves to number[]', function () {
        expect($this->service->toTsType('vector')['type'])->toBe('number[]');
    });

    test('geometry and geography resolve to unknown, never hijacked by the "int" inside their SRID subtype', function (string $native) {
        // Asserted on the parameterized form Postgres emits: the bare name would pass on the
        // fall-through default alone, so it cannot tell a map hit from a missing entry. It is also
        // what step 1a earns — "point" contains "int", which step 7's substring scan would hit first.
        expect($this->service->toTsType($native)['type'])->toBe('unknown');
    })->with(['geometry(point,4326)', 'geography(point,4326)']);

    test('set resolves to string, not string[] — MySQL returns a comma-joined value', function () {
        expect($this->service->toTsType('set')['type'])->toBe('string');
    });

    test('MySQL geometry subtypes resolve to unknown, not whatever their name accidentally substring-matches', function (string $subtype) {
        // MySqlGrammar::typeGeometry() writes the subtype itself as column_type ('point'), not
        // 'geometry(point)'. Measured without these entries: point/multipoint -> 'number' ('int'
        // substring), linestring/multilinestring -> 'string', geometrycollection -> 'unknown[]'.
        expect($this->service->toTsType($subtype)['type'])->toBe('unknown');
    })->with([
        'point', 'linestring', 'polygon', 'geometrycollection',
        'multipoint', 'multilinestring', 'multipolygon',
    ]);
});

test('maps every native type a Laravel schema grammar can emit', function (string $native, string $expected) {
    expect((new LaravelTsPublish)->toTsType($native)['type'])->toBe($expected);
})->with([
    ['tinytext', 'string'],
    ['blob', 'string'],
    ['bytea', 'string'],
    ['varbinary', 'string'],
    ['uniqueidentifier', 'string'],
    ['nvarchar', 'string'],
    ['ntext', 'string'],
    ['money', 'number'],
    ['bit', 'boolean'],
    ['xml', 'string'],
    ['interval', 'string'],
    ['serial', 'number'],
    ['bigserial', 'number'],
    ['double precision', 'number'],
]);

describe('Arrayable DTO shape inference', function () {
    test('Arrayable with array-shape toArray docblock resolves to inline object type', function () {
        $result = $this->service->toTsType(Money::class);

        expect($result['type'])->toBe('{ amount: number; currency: string }')
            ->and($result['classes'])->toBeEmpty()
            ->and($result['classFqcns'])->toBeEmpty();
    });

    test('Arrayable shape value that resolves to a class-backed token degrades that property to unknown', function () {
        // parseDocblockReturnArrayShape() carries type strings but not FQCNs, so a class token here is unimportable.
        $result = $this->service->toTsType(ArrayableWithClassValueObject::class);

        expect($result['type'])->toBe('{ owner: unknown }');
    });

    test('a class-backed value hidden inside Record<string, X> degrades the whole value; nested in array{...} degrades just the leaf', function () {
        // Record<string, User> has no shape to recurse into, so shapeValueHasUnimportableToken() degrades
        // the whole value. The nested array{owner: User} now resolves through resolveArrayShapeString(),
        // which degrades only the unimportable 'owner' leaf instead of losing the whole shape.
        $result = $this->service->toTsType(ArrayableWithHiddenClassValueObject::class);

        expect($result['type'])->toBe('{ recordOfUsers: unknown; nestedOwner: { owner: unknown } }');
    });

    test('class-backed array shape values keep degrading to unknown after the tokenizer rewrite', function () {
        // array<int, X>, X[] and Collection<int, X> all resolve to 'X[]', caught by the bracket strip.
        $result = $this->service->toTsType(ArrayableWithClassArrayValueObject::class);

        expect($result['type'])->toBe('{ listViaGeneric: unknown; listViaShorthand: unknown; listViaCollection: unknown }');
    });

    test('a nested primitive-only shape is not over-degraded by the key-stripping step', function () {
        // 'owner' is stripped as a property key, not mistaken for a value-side identifier.
        $result = $this->service->toTsType(ArrayableWithNestedPrimitiveValueObject::class);

        expect($result['type'])->toBe('{ meta: { owner: string } }');
    });

    test('subclass inheriting toArray() resolves the shape from the declaring base class docblock', function () {
        // method_exists() is true for inherited methods; getDeclaringClass() points at the class defining toArray().
        $result = $this->service->toTsType(ArrayableShapeSubclassValueObject::class);

        expect($result['type'])->toBe('{ id: number }');
    });

    test('a self-referential shape terminates instead of exhausting memory', function () {
        $result = $this->service->toTsType(ArrayableSelfReferentialValueObject::class);

        expect($result['type'])->toBe('{ id: number; child: unknown[] }');
    });

    test('a self-reference reached through a container also terminates', function () {
        $result = $this->service->toTsType(ArrayableSelfReferentialListValueObject::class);

        expect($result['type'])->toBe('{ children: unknown[][] }');
    });

    test('a mutual A to B to A shape cycle terminates', function () {
        $result = $this->service->toTsType(ArrayableMutualAValueObject::class);

        expect($result['type'])->toBe('{ b: { a: unknown[] } }');
    });

    test('the cycle guard is released, so the same DTO resolves fully on a later call', function () {
        $this->service->toTsType(ArrayableMutualAValueObject::class);

        expect($this->service->toTsType(ArrayableMutualBValueObject::class)['type'])
            ->toBe('{ a: { b: unknown[] } }');
    });

    test('Arrayable precedence wins over __toString for a DTO implementing both', function () {
        // Matches Laravel's own serialization order (Arrayable before Stringable).
        $result = $this->service->toTsType(ArrayableAndStringableValueObject::class);

        expect($result['type'])->toBe('{ value: string }');
    });

    test('Arrayable precedence wins over JsonSerializable for a DTO implementing both', function () {
        $result = $this->service->toTsType(ArrayableAndJsonSerializableValueObject::class);

        expect($result['type'])->toBe('{ fromArray: string }');
    });

    test('shapeValueHasUnimportableToken accepts a token an import already brings in', function () {
        expect($this->service->shapeValueHasUnimportableToken('RoleType'))->toBeTrue()
            ->and($this->service->shapeValueHasUnimportableToken('RoleType', ['RoleType']))->toBeFalse()
            ->and($this->service->shapeValueHasUnimportableToken('{ role: RoleType; other: Foo }', ['RoleType']))->toBeTrue();
    });

    test('shapeValueHasUnimportableToken reads an optional key as a key, not as a value', function () {
        // `?` is not a token separator, so a key stripped without it would survive as `assignedLater?`
        // and read as an unimportable value. An uninitialized typed public property emits exactly that.
        expect($this->service->shapeValueHasUnimportableToken('{ assignedLater?: string; promoted: string }'))->toBeFalse()
            ->and($this->service->shapeValueHasUnimportableToken('{ a ?: string }'))->toBeFalse()
            ->and($this->service->shapeValueHasUnimportableToken('{ nested: { deep?: number } }'))->toBeFalse()
            ->and($this->service->shapeValueHasUnimportableToken('{ owner?: User }'))->toBeTrue();
    });
});

describe('Arrayable property-shape inference', function () {
    test('typed public properties produce the object shape when no docblock shape exists', function () {
        $result = $this->service->toTsType(ArrayableData::class);

        expect($result['type'])->toBe('{ recordedAt?: string; title: string; weight: number | null }');
    });

    test('promoted readonly properties with a generic toArray docblock resolve', function () {
        $result = $this->service->toTsType(CapabilitiesDto::class);

        expect($result['type'])->toBe('{ typeName: string; tracksSteelDetails: boolean; warehouseDocsKey: string | null }');
    });

    test('a @return array{...} docblock still wins over properties', function () {
        // Money has typed public properties too, but its toArray() carries a real array{...} shape.
        $result = $this->service->toTsType(Money::class);

        expect($result['type'])->toBe('{ amount: number; currency: string }');
    });

    test('the docblock shape wins even when it diverges from the properties', function () {
        // Money's own properties happen to mirror its docblock 1:1, so that test alone can't tell
        // "docblock won" apart from "property inference happened to agree". This fixture's keys and
        // types are unrelated to its properties, so a broken precedence check fails loudly here.
        $result = $this->service->toTsType(ArrayableShapeDivergesFromPropertiesValueObject::class);

        expect($result['type'])->toBe('{ id: number; label: string }');
    });

    test('an Arrayable with no public properties stays unknown[]', function () {
        // ArrayableValueObject already covers the "no shape, no properties" fixture — see the toTsType describe block.
        $result = $this->service->toTsType(ArrayableValueObject::class);

        expect($result['type'])->toBe('unknown[]');
    });

    test('private and static properties are excluded from the shape', function () {
        $result = $this->service->toTsType(ArrayableWithNonPublicPropertiesDto::class);

        expect($result['type'])->toBe('{ visible: string }');
    });

    test('a property typed as an unimportable class degrades that property to unknown', function () {
        // No import channel exists for a bare model class token inside an inline object shape.
        $result = $this->service->toTsType(ArrayableWithClassPropertyDto::class);

        expect($result['type'])->toBe('{ label: string; owner: unknown }');
    });

    test('a property typed as the class itself terminates instead of exhausting memory', function () {
        $result = $this->service->toTsType(SelfReferentialPropertyDto::class);

        expect($result['type'])->toBe('{ label: string; child: unknown[] | null }');
    });

    test('a mutual A to B to A property shape cycle terminates', function () {
        $result = $this->service->toTsType(MutualPropertyDtoA::class);

        expect($result['type'])->toBe('{ label: string; sibling: { label: string; sibling: unknown[] | null } | null }');
    });

    test('the property cycle guard is released, so the same DTO resolves fully on a later call', function () {
        $this->service->toTsType(MutualPropertyDtoA::class);

        expect($this->service->toTsType(MutualPropertyDtoB::class)['type'])
            ->toBe('{ label: string; sibling: { label: string; sibling: unknown[] | null } | null }');
    });

    it('marks an uninitialized typed public property optional, and a promoted or defaulted one required', function () {
        $fixture = new class(2)
        {
            public int $defaulted = 1;

            public int $uninitialized;

            public function __construct(public int $promoted) {}
        };

        $type = $this->service->toTsType($fixture::class)['type'];

        expect($type)->toContain('defaulted: number')
            ->and($type)->toContain('uninitialized?: number')
            ->and($type)->toContain('promoted: number');
    });
});

describe('JsonSerializable DTO shape inference', function () {
    test('JsonSerializable with array-shape jsonSerialize docblock resolves to inline object type', function () {
        $result = $this->service->toTsType(JsonSerializableShapeValueObject::class);

        expect($result['type'])->toBe('{ id: number; label: string }');
    });

    test('JsonSerializable without docblock shape falls through to later toTsType steps', function () {
        // A bare JsonSerializable isn't guaranteed array-shaped, so it falls through instead of forcing unknown[].
        $result = $this->service->toTsType(JsonSerializablePlainValueObject::class);

        expect($result['type'])->toBe('JsonSerializablePlainValueObject')
            ->and($result['classFqcns'])->toBe([JsonSerializablePlainValueObject::class]);
    });

    test('JsonSerializable with typed public properties still falls through, unlike Arrayable', function () {
        // jsonSerialize() carries no contract tying its return value to the object's own properties
        // (unlike toArray()'s (array) $this convention), so property inference must not widen here —
        // this fixture's properties bear no relation to what jsonSerialize() actually returns.
        $result = $this->service->toTsType(JsonSerializableDivergingPropertiesValueObject::class);

        expect($result['type'])->toBe('JsonSerializableDivergingPropertiesValueObject')
            ->and($result['classFqcns'])->toBe([JsonSerializableDivergingPropertiesValueObject::class]);
    });
});

describe('plain-class property-shape inference', function () {
    test('a plain class with fully typed public properties inlines its shape', function () {
        // No file is published for Coordinate, so a class token here could never resolve to an import.
        $result = $this->service->toTsType(Coordinate::class);

        expect($result['type'])->toBe('{ lat: number; lng: number }')
            ->and($result['classes'])->toBe([])
            ->and($result['classFqcns'])->toBe([]);
    });

    test('a plain class with no public properties still emits its class token', function () {
        // The rejection branch: nothing to inline, so the token survives for acceptReflectedTypeInfo() to degrade.
        $result = $this->service->toTsType(OpaqueHandle::class);

        expect($result['type'])->toBe('OpaqueHandle')
            ->and($result['classFqcns'])->toBe([OpaqueHandle::class]);
    });

    test('Eloquent Attribute keeps its class token because its public properties are untyped', function () {
        // attributeDocblockReturnTypes() degrades a bare `@return Attribute` by matching this exact
        // classFqcns value; inlining Attribute's shape would silently disarm that check.
        $result = $this->service->toTsType(Attribute::class);

        expect($result['type'])->toBe('Attribute')
            ->and($result['classFqcns'])->toBe([Attribute::class]);
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

    test('methodReturnedTypes does not fall back to docblock', function () {
        $reflection = new ReflectionClass(DocblockReturnClass::class);
        $result = $this->service->methodReturnedTypes($reflection, 'simpleString');

        expect($result['type'])->toBe('unknown');
    });
});

describe('methodOrDocblockReturnTypes', function () {
    test('methodOrDocblockReturnTypes returns type info for a typed method', function () {
        $reflection = new ReflectionClass(Status::class);
        $result = $this->service->methodOrDocblockReturnTypes($reflection, 'icon');

        expect($result['type'])->toBe('string');
    });

    test('methodOrDocblockReturnTypes returns empty info for a missing method', function () {
        $reflection = new ReflectionClass(Status::class);
        $result = $this->service->methodOrDocblockReturnTypes($reflection, 'nonExistentMethod');

        expect($result['type'])->toBe('unknown');
    });

    test('methodOrDocblockReturnTypes falls back to @return docblock for simple type', function () {
        $reflection = new ReflectionClass(DocblockReturnClass::class);
        $result = $this->service->methodOrDocblockReturnTypes($reflection, 'simpleString');

        expect($result['type'])->toBe('string');
    });

    test('methodOrDocblockReturnTypes falls back to @return docblock for union type', function () {
        $reflection = new ReflectionClass(DocblockReturnClass::class);
        $result = $this->service->methodOrDocblockReturnTypes($reflection, 'unionStringNull');

        expect($result['type'])->toBe('string | null');
    });

    test('methodOrDocblockReturnTypes falls back to @return docblock for triple union type', function () {
        $reflection = new ReflectionClass(DocblockReturnClass::class);
        $result = $this->service->methodOrDocblockReturnTypes($reflection, 'tripleUnion');

        expect($result['type'])->toBe('number | string | null');
    });

    test('methodOrDocblockReturnTypes returns empty info when no @return tag in docblock', function () {
        $reflection = new ReflectionClass(DocblockReturnClass::class);
        $result = $this->service->methodOrDocblockReturnTypes($reflection, 'noReturnTag');

        expect($result['type'])->toBe('unknown');
    });

    test('methodOrDocblockReturnTypes returns empty info when no docblock at all', function () {
        $reflection = new ReflectionClass(DocblockReturnClass::class);
        $result = $this->service->methodOrDocblockReturnTypes($reflection, 'noDocblock');

        expect($result['type'])->toBe('unknown');
    });

    test('methodOrDocblockReturnTypes handles @return ?string nullable shorthand', function () {
        $reflection = new ReflectionClass(DocblockReturnClass::class);
        $result = $this->service->methodOrDocblockReturnTypes($reflection, 'nullableShorthand');

        expect($result['type'])->toBe('string | null');
    });
});

describe('vague signature types defer to docblock shapes', function () {
    test(': array signature with @return array{...} resolves the shape', function () {
        $info = app(LaravelTsPublish::class)->methodOrDocblockReturnTypes(
            new ReflectionClass(Order::class), 'asAutoCompleteOption',
        );

        expect($info['type'])->toBe('{ value: number; label: string }');
    });

    test('list<array{...}> resolves to an object array, not unknown[][]', function () {
        $info = app(LaravelTsPublish::class)->methodOrDocblockReturnTypes(
            new ReflectionClass(Order::class), 'presetSummaries',
        );

        expect($info['type'])->toBe('{ key: string; label: string }[]');
    });

    test('a specific signature still beats the docblock', function () {
        // : string is already specific, so it must win even though the docblock names a wider shape.
        $info = app(LaravelTsPublish::class)->methodOrDocblockReturnTypes(
            new ReflectionClass(Order::class), 'primaryLabel',
        );

        expect($info['type'])->toBe('string');
    });

    test('a nullable-prefixed generic resolves to the inner type or null', function () {
        $info = resolve(ModelAttributeResolver::class)->resolveAttribute(Order::class, 'state_ids');

        expect($info['type'])->toBe('number[] | null');
    });
});

describe('nativePhpFunctionReturnedTypes', function () {
    test('userland function with scalar return type reflects', function () {
        // route() is a userland global from illuminate/foundation's helpers.php, not a PHP-internal function.
        expect(app(LaravelTsPublish::class)->nativePhpFunctionReturnedTypes('route')['type'])
            ->toBe('string');
    });

    test('a still-internal builtin keeps resolving', function () {
        expect($this->service->nativePhpFunctionReturnedTypes('strtolower')['type'])
            ->toBe('string');
    });

    test('userland function returning a non-builtin class stays excluded', function () {
        // url() returns `UrlGenerator|string` — a union with a non-builtin member the guard must reject.
        expect($this->service->nativePhpFunctionReturnedTypes('url')['type'])
            ->toBe('unknown');
    });

    test('collect() remains excluded because it returns a Collection', function () {
        expect($this->service->nativePhpFunctionReturnedTypes('collect')['type'])
            ->toBe('unknown');
    });

    test('unknown function name resolves to unknown', function () {
        expect($this->service->nativePhpFunctionReturnedTypes('thisFunctionDoesNotExist')['type'])
            ->toBe('unknown');
    });
});

describe('docblockReturnTypes', function () {
    test('docblockReturnTypes resolves simple @return type', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'simpleString');
        $result = $this->service->docblockReturnTypes($method);

        expect($result['type'])->toBe('string');
    });

    test('docblockReturnTypes resolves union @return type', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'unionStringNull');
        $result = $this->service->docblockReturnTypes($method);

        expect($result['type'])->toBe('string | null');
    });

    test('docblockReturnTypes returns empty info when no docblock', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'noDocblock');
        $result = $this->service->docblockReturnTypes($method);

        expect($result['type'])->toBe('unknown');
    });

    test('docblockReturnTypes returns empty info when no @return tag', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'noReturnTag');
        $result = $this->service->docblockReturnTypes($method);

        expect($result['type'])->toBe('unknown');
    });

    test('docblockReturnTypes handles multiline @return array shape', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'multilineArrayShape');
        $result = $this->service->docblockReturnTypes($method);

        // resolveDocblockTypePart() now resolves a top-level array{...} shape through the same
        // shape resolver resolvePhpDocTypeToTs() uses, instead of falling through to toTsType()'s
        // partial match on the bare word "array".
        expect($result['type'])->toBe(
            '{ auth: { user: { id: number; name: string; email: string } | null }; '.
            'flash: { success: string | null; error: string | null }; appName: string }',
        );
    });

    test('docblockReturnTypes handles single-line @return array shape', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'singleLineArrayShape');
        $result = $this->service->docblockReturnTypes($method);

        expect($result['type'])->toBe('{ name: string; age: number }');
    });

    test('docblockReturnTypes degrades an unrecognized outer generic instead of guessing a partial-match type', function () {
        // docblockReturnTypes() does not unwrap Attribute<Get, Set> like attributeDocblockReturnTypes() does, so the
        // unrecognized generic must degrade rather than let toTsType() partial-match the 'int' inside it.
        $method = new ReflectionMethod(Order::class, 'sortedItems');
        $result = $this->service->docblockReturnTypes($method);

        expect($result['type'])->toBe('unknown');
    });

    test('docblockReturnTypes preserves array decoration through a nullable union', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'nullableGenericCollection');
        $result = $this->service->docblockReturnTypes($method);

        expect($result['type'])->toBe('OrderItem[] | null')
            ->and($result['classFqcns'])->toBe([OrderItem::class]);
    });
});

describe('extractReturnTypeFromDocblock', function () {
    test('extracts simple @return type', function () {
        $doc = <<<'DOC'
        /**
         * @return string
         */
        DOC;

        expect($this->service->extractReturnTypeFromDocblock($doc))->toBe('string');
    });

    test('extracts union @return type', function () {
        $doc = <<<'DOC'
        /**
         * @return string|null
         */
        DOC;

        expect($this->service->extractReturnTypeFromDocblock($doc))->toBe('string|null');
    });

    test('extracts multiline array shape', function () {
        $doc = <<<'DOC'
        /**
         * @return array{
         *     name: string,
         *     age: int
         * }
         */
        DOC;

        $result = $this->service->extractReturnTypeFromDocblock($doc);
        expect($result)->toContain('array{')
            ->and($result)->toContain('name: string')
            ->and($result)->toContain('age: int');
    });

    test('extracts deeply nested multiline array shape', function () {
        $doc = <<<'DOC'
        /**
         * @return array{
         *      auth: array{
         *          user: array{
         *              id: int,
         *              name: string
         *          }|null
         *      },
         *      appName: string
         *  }
         */
        DOC;

        $result = $this->service->extractReturnTypeFromDocblock($doc);
        expect($result)->toContain('array{')
            ->and($result)->toContain('auth:')
            ->and($result)->toContain('id: int');
    });

    test('returns null when no @return tag', function () {
        $doc = <<<'DOC'
        /**
         * Just a description.
         */
        DOC;

        expect($this->service->extractReturnTypeFromDocblock($doc))->toBeNull();
    });

    test('extracts array shape with spaced trailing union (array{...} | null)', function () {
        $doc = <<<'DOC'
        /**
         * @return array{name: string, age: int} | null
         */
        DOC;

        $result = $this->service->extractReturnTypeFromDocblock($doc);
        expect($result)->toBe('array{name: string, age: int}|null');
    });

    test('extracts multiline array shape with spaced trailing union', function () {
        $doc = <<<'DOC'
        /**
         * @return array{
         *     name: string,
         *     age: int
         * } | null
         */
        DOC;

        $result = $this->service->extractReturnTypeFromDocblock($doc);
        expect($result)->toContain('array{')
            ->and($result)->toContain('name: string')
            ->and($result)->toContain('age: int')
            ->and($result)->toContain('|null');
    });
});

describe('phpstan-return docblock support', function () {
    test('extractReturnTypeFromDocblock falls back to @phpstan-return', function () {
        $doc = "/**\n * @phpstan-return string|null\n */";

        expect(app(LaravelTsPublish::class)->extractReturnTypeFromDocblock($doc))
            ->toBe('string|null');
    });

    test('@return wins over @phpstan-return', function () {
        $doc = "/**\n * @phpstan-return int\n * @return string\n */";

        expect(app(LaravelTsPublish::class)->extractReturnTypeFromDocblock($doc))
            ->toBe('string');
    });
});

describe('psalm-return and nested generic docblock support', function () {
    test('extractReturnTypeFromDocblock falls back to @psalm-return', function () {
        $doc = "/**\n * @psalm-return string|null\n */";

        expect(app(LaravelTsPublish::class)->extractReturnTypeFromDocblock($doc))
            ->toBe('string|null');
    });

    test('extractReturnTypeFromDocblock brace-walk captures a full nested generic string', function () {
        $doc = "/**\n * @return Collection<int, array<string, int>>\n */";

        expect(app(LaravelTsPublish::class)->extractReturnTypeFromDocblock($doc))
            ->toBe('Collection<int, array<string, int>>');
    });
});

describe('generic container docblock types', function () {
    test('Collection<int, Model> narrows to an array, matching array<int, Model>', function () {
        $method = new ReflectionMethod(Order::class, 'sortedItems');
        $info = app(LaravelTsPublish::class)->attributeDocblockReturnTypes($method);

        expect($info['type'])->toBe('OrderItem[]')
            ->and($info['classFqcns'])->toBe([OrderItem::class]);
    });

    test('Collection<string, Model> resolves to a keyed record', function () {
        $method = new ReflectionMethod(Order::class, 'keyedItems');
        $info = app(LaravelTsPublish::class)->attributeDocblockReturnTypes($method);

        expect($info['type'])->toBe('Record<string, OrderItem>')
            ->and($info['classFqcns'])->toBe([OrderItem::class]);
    });

    test('list<Model> resolves to the bare array, since list<> does guarantee sequential keys', function () {
        $method = new ReflectionMethod(Order::class, 'listedItems');
        $info = app(LaravelTsPublish::class)->attributeDocblockReturnTypes($method);

        expect($info['type'])->toBe('OrderItem[]')
            ->and($info['classFqcns'])->toBe([OrderItem::class]);
    });

    test('array<string, int> resolves to Record<string, number>', function () {
        $method = new ReflectionMethod(Order::class, 'scoreMap');
        $info = app(LaravelTsPublish::class)->attributeDocblockReturnTypes($method);

        expect($info['type'])->toBe('Record<string, number>');
    });

    test('list<string> resolves to string[]', function () {
        $ts = app(LaravelTsPublish::class)->resolvePhpDocTypeToTs('list<string>', [], '');

        expect($ts)->toBe('string[]');
    });
});

describe('splitPhpDocUnionType', function () {
    test('splits simple union', function () {
        expect($this->service->splitPhpDocUnionType('string|null'))
            ->toBe(['string', 'null']);
    });

    test('returns single entry for non-union', function () {
        expect($this->service->splitPhpDocUnionType('string'))
            ->toBe(['string']);
    });

    test('respects nested braces in union', function () {
        $type = 'array{name: string|null}|null';
        $result = $this->service->splitPhpDocUnionType($type);

        expect($result)->toBe(['array{name: string|null}', 'null']);
    });
});

describe('parseDocblockReturnArrayShape', function () {
    test('parses multiline @return array shape into key-type map', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'multilineArrayShape');
        $result = $this->service->parseDocblockReturnArrayShape($method);

        // Nested array{...} shapes now resolve through resolveArrayShapeString(), the same helper
        // resolveDocblockContainerValue() and resolveDocblockTypePart() use, so the object-literal
        // separator is consistently '; ' rather than the previous inconsistent ', '.
        expect($result)->toHaveKeys(['auth', 'flash', 'appName'])
            ->and($result['appName'])->toBe('string')
            ->and($result['flash'])->toBe('{ success: string | null; error: string | null }')
            ->and($result['auth'])->toBe('{ user: { id: number; name: string; email: string } | null }');
    });

    test('parses single-line @return array shape', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'singleLineArrayShape');
        $result = $this->service->parseDocblockReturnArrayShape($method);

        expect($result)->toBe(['name' => 'string', 'age' => 'number']);
    });

    test('returns empty array for non-array-shape @return', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'simpleString');
        $result = $this->service->parseDocblockReturnArrayShape($method);

        expect($result)->toBe([]);
    });

    test('returns empty array when no docblock', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'noDocblock');
        $result = $this->service->parseDocblockReturnArrayShape($method);

        expect($result)->toBe([]);
    });
});

describe('resolvePhpDocTypeToTs', function () {
    test('resolves simple PHP types', function () {
        expect($this->service->resolvePhpDocTypeToTs('string', [], ''))
            ->toBe('string')
            ->and($this->service->resolvePhpDocTypeToTs('int', [], ''))
            ->toBe('number')
            ->and($this->service->resolvePhpDocTypeToTs('bool', [], ''))
            ->toBe('boolean');
    });

    test('resolves nullable union types', function () {
        expect($this->service->resolvePhpDocTypeToTs('string|null', [], ''))
            ->toBe('string | null');
    });

    test('resolves nested array shape', function () {
        $result = $this->service->resolvePhpDocTypeToTs('array{name: string, age: int}', [], '');

        // Semicolon-separated, matching resolveArrayShapeString() — the same formatter used by
        // resolveDocblockContainerValue() and resolveDocblockTypePart() — and real generated output.
        expect($result)->toBe('{ name: string; age: number }');
    });

    test('resolves array shape with nullable inner type', function () {
        $result = $this->service->resolvePhpDocTypeToTs('array{user: string|null}', [], '');

        expect($result)->toBe('{ user: string | null }');
    });

    test('returns Record<string, unknown> for empty array shape', function () {
        $result = $this->service->resolvePhpDocTypeToTs('array{}', [], '');

        expect($result)->toBe('Record<string, unknown>');
    });

    test('degrades an unrecognized generic instead of guessing a partial-match type', function () {
        // 'SomeGeneric' is not a recognized container, so degrade — toTsType() would partial-match the inner 'int'.
        $result = $this->service->resolvePhpDocTypeToTs('SomeGeneric<int, Foo>', [], '');

        expect($result)->toBe('unknown');
    });
});

describe('parseArrayShapeToTsTypes', function () {
    test('returns empty array when shape does not start with array{', function () {
        expect($this->service->parseArrayShapeToTsTypes('string', [], ''))->toBe([]);
    });

    test('returns empty array for empty array shape array{}', function () {
        expect($this->service->parseArrayShapeToTsTypes('array{}', [], ''))->toBe([]);
    });

    test('array shape value containing an unrecognized generic degrades to unknown instead of partial-matching', function () {
        $result = $this->service->parseArrayShapeToTsTypes('array{x: SomeGeneric<int, Foo>}', [], '');

        expect($result)->toBe(['x' => 'unknown']);
    });

    test('an optional key keeps its `?` in the returned map instead of being stripped', function () {
        $result = $this->service->parseArrayShapeToTsTypes('array{required: int, optional?: string}', [], '');

        expect($result)->toBe(['required' => 'number', 'optional?' => 'string']);
    });
});

describe('phpstan type aliases', function () {
    test('a locally-defined alias resolves', function () {
        $alias = $this->service->resolvePhpstanTypeAlias(
            'GridConfig', new ReflectionClass(GridConfigDto::class),
        );

        expect($alias)->not->toBeNull()
            ->and($alias['definition'])->toContain('filters?')
            ->and($alias['class']->getName())->toBe(GridConfigDto::class);
    });

    test('an alias declared with the = form resolves without the = in its definition', function () {
        $alias = $this->service->resolvePhpstanTypeAlias(
            'GridPreset', new ReflectionClass(GridConfigDto::class),
        );

        expect($alias)->not->toBeNull()
            ->and($alias['definition'])->toStartWith('array{')
            ->and($alias['definition'])->not->toStartWith('=')
            ->and($alias['class']->getName())->toBe(GridConfigDto::class);
    });

    test('an unknown alias name resolves to null', function () {
        $alias = $this->service->resolvePhpstanTypeAlias(
            'NoSuchAlias', new ReflectionClass(GridConfigDto::class),
        );

        expect($alias)->toBeNull();
    });

    test('a multiline alias definition is walked to its closing brace', function () {
        $alias = $this->service->resolvePhpstanTypeAlias(
            'MultilineAlias', new ReflectionClass(PhpstanTypeAliasMultilineHost::class),
        );

        expect($alias)->not->toBeNull()
            ->and($alias['definition'])->toContain('first: string')
            ->and($alias['definition'])->toContain('second: int');
    });

    test('a two-hop @phpstan-import-type chain resolves through an intermediate re-exporting class', function () {
        // X imports from Y, which only re-imports from Z (Y defines nothing locally) — proves the
        // recursion resolves all the way to the class that actually wrote the definition.
        $alias = $this->service->resolvePhpstanTypeAlias(
            'TransitiveAlias', new ReflectionClass(PhpstanTypeAliasChainX::class),
        );

        expect($alias)->not->toBeNull()
            ->and($alias['definition'])->toBe('array{depth: int}')
            ->and($alias['class']->getName())->toBe(PhpstanTypeAliasChainZ::class);
    });

    test('a cyclical @phpstan-import-type chain terminates instead of recursing forever', function () {
        $alias = $this->service->resolvePhpstanTypeAlias(
            'LoopAlias', new ReflectionClass(PhpstanTypeAliasCycleA::class),
        );

        expect($alias)->toBeNull();
    });

    test('a hyphen-suffixed sibling alias does not false-positive match its shorter prefix', function () {
        $alias = $this->service->resolvePhpstanTypeAlias(
            'PunctuationAlias', new ReflectionClass(PhpstanTypeAliasHyphenSuffixHost::class),
        );

        expect($alias)->toBeNull();
    });

    test('a dot-suffixed sibling alias does not false-positive match its shorter prefix', function () {
        $alias = $this->service->resolvePhpstanTypeAlias(
            'PunctuationAlias', new ReflectionClass(PhpstanTypeAliasDotSuffixHost::class),
        );

        expect($alias)->toBeNull();
    });
});

describe('attributeDocblockReturnTypes', function () {
    test('attributeDocblockReturnTypes resolves Attribute<string, never>', function () {
        $method = new ReflectionMethod(AttributeDocblockClass::class, 'withAttributeGeneric');
        $result = $this->service->attributeDocblockReturnTypes($method);

        expect($result['type'])->toBe('string');
    });

    test('attributeDocblockReturnTypes falls back to @return for non-Attribute docblock', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'simpleString');
        $result = $this->service->attributeDocblockReturnTypes($method);

        expect($result['type'])->toBe('string');
    });

    test('attributeDocblockReturnTypes returns empty info when no docblock', function () {
        $method = new ReflectionMethod(DocblockReturnClass::class, 'noDocblock');
        $result = $this->service->attributeDocblockReturnTypes($method);

        expect($result['type'])->toBe('unknown');
    });

    test('attributeDocblockReturnTypes resolves single-line docblock', function () {
        $method = new ReflectionMethod(AttributeDocblockClass::class, 'withAttributeSingleLine');
        $result = $this->service->attributeDocblockReturnTypes($method);

        expect($result['type'])->toBe('string');
    });

    test('attributeDocblockReturnTypes ignores @return in comment and uses actual tag', function () {
        $method = new ReflectionMethod(AttributeDocblockClass::class, 'withAttributeAndComment');
        $result = $this->service->attributeDocblockReturnTypes($method);

        expect($result['type'])->toBe('string');
    });

    test('attributeDocblockReturnTypes degrades a bare Attribute docblock instead of leaking the Attribute class', function () {
        // Without generic args the fallback parser would resolve the word "Attribute" to the Eloquent Attribute class.
        $method = new ReflectionMethod(AttributeDocblockClass::class, 'withBareAttribute');
        $result = $this->service->attributeDocblockReturnTypes($method);

        expect($result['type'])->toBe('unknown')
            ->and($result['classFqcns'])->toBe([]);
    });
});

describe('resolveReflectionType with DNF types', function () {
    test('intersection type inside union resolves to unknown', function () {
        $reflection = new ReflectionClass(DnfReturnClass::class);
        $result = $this->service->methodReturnedTypes($reflection, 'dnfMethod');

        expect($result['type'])->toContain('unknown')
            ->and($result['type'])->toContain('string');
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

describe('parseFileUseStatements', function () {
    test('resolves plain and aliased group-use members and excludes function/const imports', function () {
        require_once __DIR__.'/../Fixtures/GroupUseFixture.php.stub';

        $map = $this->service->parseFileUseStatements(new ReflectionClass(GroupUseFixture::class));

        expect($map)->toHaveCount(4)
            ->toHaveKey('Post', 'App\Models\Post')
            ->toHaveKey('Person', 'App\Models\User')
            ->toHaveKey('Widget', 'App\Support\Widget')
            ->toHaveKey('Reporter', 'App\Services\Reporting');
    });

    test('excludes a function import from the use-statement map', function () {
        // Guards the trap this task exists to avoid: a `use function` import must never
        // enter the map, or a docblock type could silently resolve to a function FQCN.
        require_once __DIR__.'/../Fixtures/GroupUseFixture.php.stub';

        $map = $this->service->parseFileUseStatements(new ReflectionClass(GroupUseFixture::class));

        expect($map)->not->toHaveKey('warning')
            ->and($map)->not->toHaveKey('helperFunction')
            ->and($map)->not->toContain('Laravel\Prompts\warning');
    });

    test('records the file as a cache dependency even on a cache hit', function () {
        // Guards the same cache-hit-must-still-record contract AstParserTest pins for AstParser:
        // useStatementsCache sits above it and must not swallow the recording on a hit.
        require_once __DIR__.'/../Fixtures/GroupUseFixture.php.stub';

        $reflection = new ReflectionClass(GroupUseFixture::class);
        $file = (string) $reflection->getFileName();

        DependencyRecorder::start();
        $this->service->parseFileUseStatements($reflection);
        DependencyRecorder::reset();
        $this->service->parseFileUseStatements($reflection);
        $paths = DependencyRecorder::paths();
        DependencyRecorder::stop();
        DependencyRecorder::reset();

        expect($paths)->toContain($file);
    });
});

describe('resolveReflectionType with DNF union types', function () {
    test('resolveReflectionType handles DNF union with intersection member', function () {
        $closure = fn (): (\Countable&\Iterator)|string => 'hello';
        $returnType = (new ReflectionFunction($closure))->getReturnType();

        $result = $this->service->resolveReflectionType($returnType);

        expect($result['type'])->toBe('unknown | string');
    });

    test('union type with customImports merges imports from named members', function () {
        // MenuSettings has #[TsType(['type' => 'MenuSettingsType', 'import' => '@js/types/settings'])]
        $closure = fn (): MenuSettings|string => 'hello';
        $returnType = (new ReflectionFunction($closure))->getReturnType();

        $result = $this->service->resolveReflectionType($returnType);

        expect($result['type'])->toContain('MenuSettingsType')
            ->and($result['customImports'])->toHaveKey('@js/types/settings');
    });
});

describe('TS_PRIMITIVES', function () {
    test('the public LaravelTsPublish::TS_PRIMITIVES name still resolves', function () {
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

describe('mergeTypeScriptInfos', function () {
    test('preserves a separate type token for each unique FQCN even when basenames are the same', function () {
        $infoA = [...$this->service->emptyTypeScriptInfo(), 'type' => 'User', 'classes' => ['User'], 'classFqcns' => ['App\\Models\\User']];
        $infoB = [...$this->service->emptyTypeScriptInfo(), 'type' => 'User', 'classes' => ['User'], 'classFqcns' => ['Crm\\Models\\User']];
        $infoNull = [...$this->service->emptyTypeScriptInfo(), 'type' => 'null'];

        $result = $this->service->mergeTypeScriptInfos([$infoA, $infoB, $infoNull]);

        expect($result['type'])->toBe('User | User | null')
            ->and($result['classes'])->toBe(['User', 'User'])
            ->and($result['classFqcns'])->toBe(['App\\Models\\User', 'Crm\\Models\\User']);
    });

    test('deduplicates when the same FQCN appears twice', function () {
        $info = [...$this->service->emptyTypeScriptInfo(), 'type' => 'User', 'classes' => ['User'], 'classFqcns' => ['App\\Models\\User']];

        $result = $this->service->mergeTypeScriptInfos([$info, $info]);

        expect($result['type'])->toBe('User')
            ->and($result['classes'])->toBe(['User'])
            ->and($result['classFqcns'])->toBe(['App\\Models\\User']);
    });

    test('deduplicates non-class type tokens by type string', function () {
        $infoA = [...$this->service->emptyTypeScriptInfo(), 'type' => 'string'];
        $infoB = [...$this->service->emptyTypeScriptInfo(), 'type' => 'string'];
        $infoNull = [...$this->service->emptyTypeScriptInfo(), 'type' => 'null'];

        $result = $this->service->mergeTypeScriptInfos([$infoA, $infoB, $infoNull]);

        expect($result['type'])->toBe('string | null');
    });

    test('preserves array-container decoration on a class-backed type merged with null', function () {
        // Decomposing a container-decorated info to its bare class name would silently drop the '[]'.
        $infoArray = [...$this->service->emptyTypeScriptInfo(), 'type' => 'OrderItem[]', 'classes' => ['OrderItem'], 'classFqcns' => [OrderItem::class]];
        $infoNull = [...$this->service->emptyTypeScriptInfo(), 'type' => 'null'];

        $result = $this->service->mergeTypeScriptInfos([$infoArray, $infoNull]);

        expect($result['type'])->toBe('OrderItem[] | null')
            ->and($result['classes'])->toBe(['OrderItem'])
            ->and($result['classFqcns'])->toBe([OrderItem::class]);
    });

    test('preserves Record-container decoration on a class-backed type merged with null', function () {
        $infoRecord = [...$this->service->emptyTypeScriptInfo(), 'type' => 'Record<string, OrderItem>', 'classes' => ['OrderItem'], 'classFqcns' => [OrderItem::class]];
        $infoNull = [...$this->service->emptyTypeScriptInfo(), 'type' => 'null'];

        $result = $this->service->mergeTypeScriptInfos([$infoRecord, $infoNull]);

        expect($result['type'])->toBe('Record<string, OrderItem> | null');
    });

    test('keeps distinct decorated tokens for two different container-decorated classes', function () {
        $infoA = [...$this->service->emptyTypeScriptInfo(), 'type' => 'A[]', 'classes' => ['A'], 'classFqcns' => ['App\\Models\\A']];
        $infoB = [...$this->service->emptyTypeScriptInfo(), 'type' => 'B[]', 'classes' => ['B'], 'classFqcns' => ['App\\Models\\B']];

        $result = $this->service->mergeTypeScriptInfos([$infoA, $infoB]);

        expect($result['type'])->toBe('A[] | B[]')
            ->and($result['classes'])->toBe(['A', 'B'])
            ->and($result['classFqcns'])->toBe(['App\\Models\\A', 'App\\Models\\B']);
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

describe('callCommandUsing and callCommandWith', function () {
    afterEach(function () {
        $prop = (new ReflectionClass(LaravelTsPublish::class))->getProperty('callCommandWith');
        $prop->setValue(null, null);
    });

    test('callCommandWith does nothing when no closure is registered', function () {
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
            config()->set('ts-publish.models.additional_directories', ['modules/Blog/Models']);
        });

        expect(config('ts-publish.models.additional_directories'))->not->toBe(['modules/Blog/Models']);

        $this->service->callCommandWith();

        expect(config('ts-publish.models.additional_directories'))->toBe(['modules/Blog/Models']);
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

    test('resolves class from relative file path via base_path', function () {
        // Pass a relative path (no leading /) so base_path() is invoked
        $result = $this->service->resolveClassFromFile('some/nonexistent/file.php');

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

/**
 * A class with a method returning a DNF type (PHP 8.2+), for a ReflectionIntersectionType inside a union.
 */
class DnfReturnClass
{
    public function dnfMethod(): (Countable&Iterator)|string // @phpstan-ignore missingType.iterableValue
    {
        return 'hello';
    }
}

/**
 * A value object implementing Arrayable for testing step 5a resolution.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableValueObject implements Arrayable
{
    public function toArray(): array
    {
        return [];
    }
}

/**
 * A value object with __toString for testing step 5b resolution.
 */
class StringableValueObject
{
    public function __toString(): string
    {
        return 'value';
    }
}

/**
 * A value object implementing Arrayable whose toArray() shape references a class-backed value type.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableWithClassValueObject implements Arrayable
{
    /** @return array{owner: User} */
    public function toArray(): array
    {
        return [];
    }
}

/**
 * A value object whose toArray() shape hides class tokens inside array<string, X> and a nested array{...}.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableWithHiddenClassValueObject implements Arrayable
{
    /**
     * @return array{
     *     recordOfUsers: array<string, User>,
     *     nestedOwner: array{owner: User}
     * }
     */
    public function toArray(): array
    {
        return [];
    }
}

/**
 * A value object whose toArray() shape uses the three class-backed array forms that all resolve to 'X[]'.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableWithClassArrayValueObject implements Arrayable
{
    /**
     * @return array{
     *     listViaGeneric: array<int, User>,
     *     listViaShorthand: User[],
     *     listViaCollection: Collection<int, User>
     * }
     */
    public function toArray(): array
    {
        return [];
    }
}

/**
 * A value object whose toArray() shape nests a primitive-only array{...} under a non-primitive key name.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableWithNestedPrimitiveValueObject implements Arrayable
{
    /** @return array{meta: array{owner: string}} */
    public function toArray(): array
    {
        return [];
    }
}

/**
 * A value object whose toArray() shape names itself, so shape expansion would recurse forever.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableSelfReferentialValueObject implements Arrayable
{
    /** @return array{id: int, child: ArrayableSelfReferentialValueObject} */
    public function toArray(): array
    {
        return [];
    }
}

/**
 * The same cycle reached through a container rather than a bare class name.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableSelfReferentialListValueObject implements Arrayable
{
    /** @return array{children: ArrayableSelfReferentialListValueObject[]} */
    public function toArray(): array
    {
        return [];
    }
}

/**
 * Half of a mutual A -> B -> A shape cycle.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableMutualAValueObject implements Arrayable
{
    /** @return array{b: ArrayableMutualBValueObject} */
    public function toArray(): array
    {
        return [];
    }
}

/**
 * The other half of the mutual A -> B -> A shape cycle.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableMutualBValueObject implements Arrayable
{
    /** @return array{a: ArrayableMutualAValueObject} */
    public function toArray(): array
    {
        return [];
    }
}

/**
 * An Arrayable DTO with no docblock shape and a mix of visibility/staticness, for property-based
 * shape inference: only public, non-static properties (promoted ones included) belong in the shape.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableWithNonPublicPropertiesDto implements Arrayable
{
    public static string $shared = 'static';

    protected string $internal = 'hidden';

    private string $secret = 'hidden';

    public function __construct(
        public string $visible = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['visible' => $this->visible];
    }
}

/**
 * An Arrayable DTO whose property is typed as a Model class — no import channel exists for a bare
 * class token inside an inline shape, so property-based inference must degrade it to unknown.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableWithClassPropertyDto implements Arrayable
{
    public function __construct(
        public string $label = '',
        public ?User $owner = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['label' => $this->label, 'owner' => $this->owner];
    }
}

/**
 * A property typed as the class itself: the property-based shapeExpansionStack guard must
 * terminate this instead of recursing until memory is exhausted.
 *
 * @implements Arrayable<string, mixed>
 */
class SelfReferentialPropertyDto implements Arrayable
{
    public function __construct(
        public string $label = '',
        public ?SelfReferentialPropertyDto $child = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return (array) $this;
    }
}

/**
 * Half of a mutual A -> B -> A property-shape cycle.
 *
 * @implements Arrayable<string, mixed>
 */
class MutualPropertyDtoA implements Arrayable
{
    public function __construct(
        public string $label = '',
        public ?MutualPropertyDtoB $sibling = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return (array) $this;
    }
}

/**
 * The other half of the mutual A -> B -> A property-shape cycle.
 *
 * @implements Arrayable<string, mixed>
 */
class MutualPropertyDtoB implements Arrayable
{
    public function __construct(
        public string $label = '',
        public ?MutualPropertyDtoA $sibling = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return (array) $this;
    }
}

/**
 * An Arrayable DTO whose toArray() shape docblock deliberately names different keys/types than its
 * public properties, so a precedence test can tell "docblock won" apart from "property inference
 * happened to produce the same result" — Money's own properties happen to mirror its docblock 1:1.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableShapeDivergesFromPropertiesValueObject implements Arrayable
{
    public function __construct(
        public string $internalName = '',
        public bool $internalActive = false,
    ) {}

    /** @return array{id: int, label: string} */
    public function toArray(): array
    {
        return ['id' => 1, 'label' => 'value'];
    }
}

/**
 * A JsonSerializable DTO whose jsonSerialize() output has no relation to its public properties —
 * proves property-shape inference does not widen to the JsonSerializable path, where
 * jsonSerialize() carries no contract tying its return value to the object's own properties.
 */
class JsonSerializableDivergingPropertiesValueObject implements JsonSerializable
{
    public function __construct(
        public string $internalToken = 'secret',
    ) {}

    public function jsonSerialize(): mixed
    {
        return ['totally' => 'unrelated'];
    }
}

/**
 * An Arrayable base class carrying the shape docblock its subclass inherits rather than overrides.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableShapeBaseValueObject implements Arrayable
{
    /** @return array{id: int} */
    public function toArray(): array
    {
        return ['id' => 1];
    }
}

/**
 * Inherits toArray() from ArrayableShapeBaseValueObject without overriding it.
 */
class ArrayableShapeSubclassValueObject extends ArrayableShapeBaseValueObject {}

/**
 * A value object implementing both Arrayable (with a shape docblock) and Stringable.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableAndStringableValueObject implements Arrayable, Stringable
{
    /** @return array{value: string} */
    public function toArray(): array
    {
        return ['value' => 'hello'];
    }

    public function __toString(): string
    {
        return 'hello';
    }
}

/**
 * A value object implementing Arrayable and JsonSerializable with a different shape docblock on each.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableAndJsonSerializableValueObject implements Arrayable, JsonSerializable
{
    /** @return array{fromArray: string} */
    public function toArray(): array
    {
        return ['fromArray' => 'value'];
    }

    /** @return array{fromJson: string} */
    public function jsonSerialize(): array
    {
        return ['fromJson' => 'value'];
    }
}

/**
 * A value object implementing JsonSerializable (not Arrayable) with an array-shape jsonSerialize() docblock.
 */
class JsonSerializableShapeValueObject implements JsonSerializable
{
    /** @return array{id: int, label: string} */
    public function jsonSerialize(): array
    {
        return ['id' => 1, 'label' => 'value'];
    }
}

/**
 * A value object implementing JsonSerializable with no array-shape docblock.
 */
class JsonSerializablePlainValueObject implements JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        return null;
    }
}

/**
 * Fixture class with methods that have @return docblocks but no PHP return type hints.
 */
class DocblockReturnClass
{
    /**
     * @return string
     */
    public function simpleString()
    {
        return 'hello';
    }

    /**
     * @return string|null
     */
    public function unionStringNull()
    {
        return null;
    }

    /**
     * @return int|string|null
     */
    public function tripleUnion()
    {
        return 42;
    }

    /** No @return tag here. */
    public function noReturnTag()
    {
        return 'hello';
    }

    public function noDocblock()
    {
        return 'hello';
    }

    /**
     * @return ?string
     */
    public function nullableShorthand()
    {
        return null;
    }

    /**
     * @return array{
     *      auth: array{
     *          user: array{
     *              id: int,
     *              name: string,
     *              email: string
     *          }|null
     *      },
     *      flash: array{
     *          success: string|null,
     *          error: string|null
     *      },
     *      appName: string
     *  }
     */
    public function multilineArrayShape(): array
    {
        return [];
    }

    /**
     * @return array{name: string, age: int}
     */
    public function singleLineArrayShape(): array
    {
        return [];
    }

    /**
     * @return Collection<int, OrderItem>|null
     */
    public function nullableGenericCollection()
    {
        return null;
    }
}

class AttributeDocblockClass
{
    /**
     * @return Attribute<string, never>
     */
    public function withAttributeGeneric()
    {
        return null;
    }

    /** @return Attribute<string, never> */
    public function withAttributeSingleLine()
    {
        return '';
    }

    /**
     * The @return Attribute<number, never> doc block should be string.
     *
     * @return Attribute<string, never>
     */
    public function withAttributeAndComment()
    {
        return '';
    }

    /** @return Attribute */
    public function withBareAttribute()
    {
        return null;
    }
}

/**
 * A @phpstan-type definition spanning multiple docblock lines, proving resolvePhpstanTypeAlias()
 * walks lines until the array{...} shape's brace depth closes rather than truncating at the first.
 *
 * @phpstan-type MultilineAlias array{
 *     first: string,
 *     second: int,
 * }
 */
class PhpstanTypeAliasMultilineHost {}

/**
 * X of a three-class @phpstan-import-type chain (X -> Y -> Z): imports TransitiveAlias from Y,
 * which only re-exports it from Z — proves resolution reaches through an intermediate class that
 * never defines the alias itself.
 *
 * @phpstan-import-type TransitiveAlias from PhpstanTypeAliasChainY
 */
class PhpstanTypeAliasChainX {}

/**
 * Y: re-exports TransitiveAlias from Z without ever defining it locally.
 *
 * @phpstan-import-type TransitiveAlias from PhpstanTypeAliasChainZ
 */
class PhpstanTypeAliasChainY {}

/**
 * Z: where TransitiveAlias is actually defined.
 *
 * @phpstan-type TransitiveAlias array{depth: int}
 */
class PhpstanTypeAliasChainZ {}

/**
 * Half of a mutual @phpstan-import-type cycle: LoopAlias here is imported from CycleB, which
 * imports it back from here — resolvePhpstanTypeAlias() must terminate instead of recursing forever.
 *
 * @phpstan-import-type LoopAlias from PhpstanTypeAliasCycleB
 */
class PhpstanTypeAliasCycleA {}

/**
 * The other half of the mutual @phpstan-import-type cycle.
 *
 * @phpstan-import-type LoopAlias from PhpstanTypeAliasCycleA
 */
class PhpstanTypeAliasCycleB {}

/**
 * Declares only a hyphen-suffixed sibling of PunctuationAlias, never PunctuationAlias itself:
 * proves \b's word/non-word boundary alone can't be trusted to reject a punctuation continuation.
 *
 * @phpstan-type PunctuationAlias-Suffix array{x: int}
 */
class PhpstanTypeAliasHyphenSuffixHost {}

/**
 * Declares only a dot-suffixed sibling of PunctuationAlias, never PunctuationAlias itself: the
 * same false-positive class as the hyphen case, with a different non-word continuation character.
 *
 * @phpstan-type PunctuationAlias.Suffix array{x: int}
 */
class PhpstanTypeAliasDotSuffixHost {}
