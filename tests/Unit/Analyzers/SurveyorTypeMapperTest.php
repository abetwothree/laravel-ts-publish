<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\SurveyorTypeMapper;
use Laravel\Surveyor\Types;
use Laravel\Surveyor\Types\Contracts\Type;

// ─── convert() scalar types ───────────────────────────────────────

test('converts StringType to "string"', function () {
    expect(SurveyorTypeMapper::convert(new Types\StringType))->toBe('string');
});

test('converts nullable StringType to "string | null"', function () {
    $type = (new Types\StringType)->nullable();

    expect(SurveyorTypeMapper::convert($type))->toBe('string | null');
});

test('converts IntType to "number"', function () {
    expect(SurveyorTypeMapper::convert(new Types\IntType))->toBe('number');
});

test('converts FloatType to "number"', function () {
    expect(SurveyorTypeMapper::convert(new Types\FloatType))->toBe('number');
});

test('converts NumberType to "number"', function () {
    expect(SurveyorTypeMapper::convert(new Types\NumberType))->toBe('number');
});

test('converts nullable IntType to "number | null"', function () {
    $type = (new Types\IntType)->nullable();

    expect(SurveyorTypeMapper::convert($type))->toBe('number | null');
});

test('converts BoolType without value to "boolean"', function () {
    expect(SurveyorTypeMapper::convert(new Types\BoolType))->toBe('boolean');
});

test('converts BoolType with true value to "true"', function () {
    expect(SurveyorTypeMapper::convert(new Types\BoolType(true)))->toBe('true');
});

test('converts BoolType with false value to "false"', function () {
    expect(SurveyorTypeMapper::convert(new Types\BoolType(false)))->toBe('false');
});

test('converts nullable BoolType to "boolean | null"', function () {
    $type = (new Types\BoolType)->nullable();

    expect(SurveyorTypeMapper::convert($type))->toBe('boolean | null');
});

test('converts NullType to "null"', function () {
    expect(SurveyorTypeMapper::convert(new Types\NullType))->toBe('null');
});

test('converts MixedType to "unknown"', function () {
    expect(SurveyorTypeMapper::convert(new Types\MixedType))->toBe('unknown');
});

// ─── convert() class types ────────────────────────────────────────

test('converts ClassType with Stringable to "string"', function () {
    $type = new Types\ClassType('Illuminate\\Support\\Stringable');

    expect(SurveyorTypeMapper::convert($type))->toBe('string');
});

test('converts ClassType with Collection to "unknown[]"', function () {
    $type = new Types\ClassType('Illuminate\\Support\\Collection');

    expect(SurveyorTypeMapper::convert($type))->toBe('unknown[]');
});

test('converts ClassType with arbitrary class to dot-separated name', function () {
    $type = new Types\ClassType('App\\Models\\User');

    expect(SurveyorTypeMapper::convert($type))->toBe('App.Models.User');
});

test('converts nullable ClassType to include null suffix', function () {
    $type = (new Types\ClassType('App\\Models\\User'))->nullable();

    expect(SurveyorTypeMapper::convert($type))->toBe('App.Models.User | null');
});

test('strips leading backslash from ClassType', function () {
    $type = new Types\ClassType('\\App\\Models\\Post');

    expect(SurveyorTypeMapper::convert($type))->toBe('App.Models.Post');
});

// ─── convert() compound types ─────────────────────────────────────

test('converts UnionType to pipe-separated types', function () {
    $type = new Types\UnionType([new Types\StringType, new Types\IntType]);

    expect(SurveyorTypeMapper::convert($type))->toBe('string | number');
});

test('converts IntersectionType to ampersand-separated types', function () {
    $type = new Types\IntersectionType([new Types\StringType, new Types\IntType]);

    expect(SurveyorTypeMapper::convert($type))->toBe('string & number');
});

test('union with unknown and concrete type simplifies to concrete only', function () {
    $type = new Types\UnionType([new Types\StringType, new Types\MixedType]);

    expect(SurveyorTypeMapper::convert($type))->toBe('string');
});

test('union with only unknown and null simplifies to unknown', function () {
    $type = new Types\UnionType([new Types\MixedType, new Types\NullType]);

    expect(SurveyorTypeMapper::convert($type))->toBe('unknown');
});

test('union with null items filters them out', function () {
    $type = new Types\UnionType([new Types\StringType, null]);

    expect(SurveyorTypeMapper::convert($type))->toBe('string');
});

test('union with nested array of types produces sub-union', function () {
    $type = new Types\UnionType([[new Types\StringType, new Types\IntType]]);

    expect(SurveyorTypeMapper::convert($type))->toBe('string | number');
});

test('union with nested array containing null values filters them', function () {
    $type = new Types\UnionType([[new Types\StringType, null]]);

    expect(SurveyorTypeMapper::convert($type))->toBe('string');
});

test('union with non-Type item returns unknown', function () {
    $type = new Types\UnionType(['not-a-type']);

    expect(SurveyorTypeMapper::convert($type))->toBe('unknown');
});

// ─── convert() array types ────────────────────────────────────────

test('converts list ArrayType to element type with brackets', function () {
    $type = new Types\ArrayType([new Types\StringType]);

    expect(SurveyorTypeMapper::convert($type))->toBe('string[]');
});

test('converts list ArrayType with multiple types to union brackets', function () {
    $type = new Types\ArrayType([new Types\StringType, new Types\IntType]);

    expect(SurveyorTypeMapper::convert($type))->toBe('(string | number)[]');
});

test('converts nullable list ArrayType to include null suffix', function () {
    $type = (new Types\ArrayType([new Types\StringType]))->nullable();

    expect(SurveyorTypeMapper::convert($type))->toBe('string[] | null');
});

test('converts associative ArrayType to object literal', function () {
    $type = new Types\ArrayType([
        'name' => new Types\StringType,
        'age' => new Types\IntType,
    ]);

    expect(SurveyorTypeMapper::convert($type))->toBe('{ name: string, age: number }');
});

test('converts nullable associative ArrayType to include null suffix', function () {
    $type = (new Types\ArrayType([
        'key' => new Types\StringType,
    ]))->nullable();

    expect(SurveyorTypeMapper::convert($type))->toBe('{ key: string } | null');
});

test('converts ArrayType with non-Type list items to unknown[]', function () {
    $type = new Types\ArrayType(['not-a-type']);

    expect(SurveyorTypeMapper::convert($type))->toBe('unknown[]');
});

// ─── convert() ArrayShapeType ─────────────────────────────────────

test('converts ArrayShapeType with number key to value[]', function () {
    $type = new Types\ArrayShapeType(new Types\IntType, new Types\StringType);

    expect(SurveyorTypeMapper::convert($type))->toBe('string[]');
});

test('converts ArrayShapeType with string key to Record', function () {
    $type = new Types\ArrayShapeType(new Types\StringType, new Types\IntType);

    expect(SurveyorTypeMapper::convert($type))->toBe('Record<string, number>');
});

test('converts ArrayShapeType with unknown key to Record with string key', function () {
    $type = new Types\ArrayShapeType(new Types\MixedType, new Types\StringType);

    expect(SurveyorTypeMapper::convert($type))->toBe('Record<string, string>');
});

test('converts nullable ArrayShapeType with number key to include null suffix', function () {
    $type = (new Types\ArrayShapeType(new Types\IntType, new Types\StringType))->nullable();

    expect(SurveyorTypeMapper::convert($type))->toBe('string[] | null');
});

test('converts nullable ArrayShapeType with string key to include null suffix', function () {
    $type = (new Types\ArrayShapeType(new Types\StringType, new Types\IntType))->nullable();

    expect(SurveyorTypeMapper::convert($type))->toBe('Record<string, number> | null');
});

// ─── convert() CallableType ───────────────────────────────────────

test('converts CallableType to its return type', function () {
    $type = new Types\CallableType([], new Types\StringType);

    expect(SurveyorTypeMapper::convert($type))->toBe('string');
});

test('converts CallableType with no return type to unknown', function () {
    $type = new Types\CallableType([]);

    expect(SurveyorTypeMapper::convert($type))->toBe('unknown');
});

// ─── convert() unsupported type ───────────────────────────────────

test('throws InvalidArgumentException for unsupported type', function () {
    $type = new Types\VoidType;

    SurveyorTypeMapper::convert($type);
})->throws(InvalidArgumentException::class, 'Unsupported Surveyor type');

// ─── objectToTypeString() ─────────────────────────────────────────

test('objectToTypeString returns Record<string, never> for empty array', function () {
    expect(SurveyorTypeMapper::objectToTypeString([]))->toBe('Record<string, never>');
});

test('objectToTypeString converts typed props to object literal', function () {
    $result = SurveyorTypeMapper::objectToTypeString([
        'name' => new Types\StringType,
        'count' => new Types\IntType,
    ]);

    expect($result)->toBe('{ name: string, count: number }');
});

test('objectToTypeString marks optional types with ?', function () {
    $result = SurveyorTypeMapper::objectToTypeString([
        'required' => new Types\StringType,
        'optional' => (new Types\StringType)->optional(),
    ]);

    expect($result)->toContain('required: string')
        ->toContain('optional?: string');
});

test('objectToTypeString handles nested arrays recursively', function () {
    $result = SurveyorTypeMapper::objectToTypeString([
        'nested' => ['inner' => new Types\IntType],
    ]);

    expect($result)->toBe('{ nested: { inner: number } }');
});

test('objectToTypeString converts non-Type values to unknown', function () {
    $result = SurveyorTypeMapper::objectToTypeString([
        'weird' => 'not-a-type',
    ]);

    expect($result)->toBe('{ weird: unknown }');
});

test('objectToTypeString handles nullable types', function () {
    $result = SurveyorTypeMapper::objectToTypeString([
        'value' => (new Types\StringType)->nullable(),
    ]);

    expect($result)->toBe('{ value: string | null }');
});

// ─── TOLKI_TYPES_MAP ──────────────────────────────────────────────

test('TOLKI_TYPES_MAP contains all pagination concrete classes and interfaces', function () {
    expect(SurveyorTypeMapper::TOLKI_TYPES_MAP)
        ->toHaveKey('Illuminate\\Pagination\\LengthAwarePaginator', 'LengthAwarePaginator')
        ->toHaveKey('Illuminate\\Pagination\\Paginator', 'SimplePaginator')
        ->toHaveKey('Illuminate\\Pagination\\CursorPaginator', 'CursorPaginator')
        ->toHaveKey('Illuminate\\Contracts\\Pagination\\LengthAwarePaginator', 'LengthAwarePaginator')
        ->toHaveKey('Illuminate\\Contracts\\Pagination\\Paginator', 'SimplePaginator')
        ->toHaveKey('Illuminate\\Contracts\\Pagination\\CursorPaginator', 'CursorPaginator')
        ->toHaveKey('Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection', 'AnonymousResourceCollection');
});

test('converts LengthAwarePaginator ClassType to dot-notation with <unknown>', function () {
    $type = new Types\ClassType('Illuminate\\Pagination\\LengthAwarePaginator');

    expect(SurveyorTypeMapper::convert($type))
        ->toBe('Illuminate.Pagination.LengthAwarePaginator<unknown>');
});

test('converts TOLKI_TYPES_MAP ClassType with Surveyor genericTypes to concrete generic suffix', function () {
    $type = new Types\ClassType('Illuminate\\Pagination\\LengthAwarePaginator');
    $type->setGenericTypes([new Types\ClassType('App\\Models\\Post')]);

    expect(SurveyorTypeMapper::convert($type))
        ->toBe('Illuminate.Pagination.LengthAwarePaginator<App.Models.Post>');
});

test('converts TOLKI_TYPES_MAP ClassType with multiple Surveyor genericTypes', function () {
    $type = new Types\ClassType('Illuminate\\Pagination\\LengthAwarePaginator');
    $type->setGenericTypes([new Types\ClassType('App\\Models\\Post'), new Types\StringType]);

    expect(SurveyorTypeMapper::convert($type))
        ->toBe('Illuminate.Pagination.LengthAwarePaginator<App.Models.Post, string>');
});

test('converts Paginator contract ClassType to dot-notation with <unknown>', function () {
    $type = new Types\ClassType('Illuminate\\Contracts\\Pagination\\Paginator');

    expect(SurveyorTypeMapper::convert($type))
        ->toBe('Illuminate.Contracts.Pagination.Paginator<unknown>');
});

test('converts CursorPaginator contract ClassType to dot-notation with <unknown>', function () {
    $type = new Types\ClassType('Illuminate\\Contracts\\Pagination\\CursorPaginator');

    expect(SurveyorTypeMapper::convert($type))
        ->toBe('Illuminate.Contracts.Pagination.CursorPaginator<unknown>');
});

test('converts AnonymousResourceCollection ClassType to dot-notation with <unknown>', function () {
    $type = new Types\ClassType('Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection');

    expect(SurveyorTypeMapper::convert($type))
        ->toBe('Illuminate.Http.Resources.Json.AnonymousResourceCollection<unknown>');
});

// ─── extractDotNotationFqcns() ────────────────────────────────────

test('extractDotNotationFqcns extracts PHP interface FQCNs from type string', function () {
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Contracts.Pagination.Paginator<unknown> }';
    $fqcns = SurveyorTypeMapper::extractDotNotationFqcns($typeString);

    expect($fqcns)->toContain('Illuminate\\Contracts\\Pagination\\Paginator');
});

test('extractDotNotationFqcns extracts CursorPaginator contract FQCN', function () {
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Contracts.Pagination.CursorPaginator<unknown> }';
    $fqcns = SurveyorTypeMapper::extractDotNotationFqcns($typeString);

    expect($fqcns)->toContain('Illuminate\\Contracts\\Pagination\\CursorPaginator');
});

test('extractDotNotationFqcns extracts AnonymousResourceCollection FQCN', function () {
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Http.Resources.Json.AnonymousResourceCollection<unknown> }';
    $fqcns = SurveyorTypeMapper::extractDotNotationFqcns($typeString);

    expect($fqcns)->toContain('Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection');
});

// ─── rewriteDotNotationToBasenames() ─────────────────────────────

test('rewriteDotNotationToBasenames uses TOLKI_TYPES_MAP name for Paginator contract', function () {
    $fqcns = ['Illuminate\\Contracts\\Pagination\\Paginator'];
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Contracts.Pagination.Paginator<unknown> }';

    $result = SurveyorTypeMapper::rewriteDotNotationToBasenames($typeString, $fqcns);

    expect($result)->toBe('Inertia.SharedData & { posts: SimplePaginator<unknown> }');
});

test('rewriteDotNotationToBasenames uses TOLKI_TYPES_MAP name for AnonymousResourceCollection', function () {
    $fqcns = ['Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection'];
    $typeString = 'Inertia.SharedData & { posts: Illuminate.Http.Resources.Json.AnonymousResourceCollection<unknown> }';

    $result = SurveyorTypeMapper::rewriteDotNotationToBasenames($typeString, $fqcns);

    expect($result)->toBe('Inertia.SharedData & { posts: AnonymousResourceCollection<unknown> }');
});

test('rewriteDotNotationToBasenames uses basename for non-mapped classes', function () {
    $fqcns = ['Workbench\\App\\Models\\Post'];
    $typeString = 'Inertia.SharedData & { post: Workbench.App.Models.Post }';

    $result = SurveyorTypeMapper::rewriteDotNotationToBasenames($typeString, $fqcns);

    expect($result)->toBe('Inertia.SharedData & { post: Post }');
});
