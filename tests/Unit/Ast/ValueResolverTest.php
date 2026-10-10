<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\InertiaPropertyLabel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ResponsableLabel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ArrayJsonCarbon;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ArrayJsonDate;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NullableStringJson;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\StringJsonArrayable;
use Carbon\Carbon as CarbonCarbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Stringable;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\ClassConstantResource;
use Workbench\App\Models\User;
use Workbench\App\Services\ChannelDefaults;
use Workbench\App\ValueObjects\ArrayableData;
use Workbench\App\ValueObjects\Coordinate;
use Workbench\App\ValueObjects\Money;
use Workbench\App\ValueObjects\OpaqueHandle;
use Workbench\App\ValueObjects\StampedDate;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A throwaway scope carrying ClassConstantResource, so self/static/parent constants resolve too.
 */
function valueResolverTestScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ClassConstantResource::class));
}

/**
 * Parse one PHP expression, names resolved as the analyzer resolves them.
 */
function valueResolverParse(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/**
 * Build a `Fqcn::CONST_NAME` ClassConstFetch node.
 *
 * @param  class-string  $fqcn
 */
function valueResolverClassConstFetch(string $fqcn, string $constName): ClassConstFetch
{
    return new ClassConstFetch(new Name($fqcn), new Identifier($constName));
}

// resolveClassConstant() — scalar, list, and record constants

it('resolves a scalar constant to its literal type', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'MAX_RETRIES');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope());

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('resolves self:: and parent:: constants via the scope subject reflection', function () {
    $resolver = new ValueResolver;
    $scope = valueResolverTestScope();

    $selfResult = $resolver->resolveClassConstant(
        valueResolverClassConstFetch('self', 'SCHEMA_VERSION'), $scope,
    );
    $parentResult = $resolver->resolveClassConstant(
        valueResolverClassConstFetch('parent', 'BASE_VERSION'), $scope,
    );

    expect($selfResult)->toBe(['type' => 'number', 'optional' => false])
        ->and($parentResult)->toBe(['type' => 'number', 'optional' => false]);
});

it('resolves a plain-list constant to an element-union array', function () {
    $resolver = new ValueResolver;
    $scope = valueResolverTestScope();

    $agreeing = $resolver->resolveClassConstant(
        valueResolverClassConstFetch(ChannelDefaults::class, 'CHANNEL_TAGS'), $scope,
    );
    $disagreeing = $resolver->resolveClassConstant(
        valueResolverClassConstFetch(ChannelDefaults::class, 'MIXED_TAGS'), $scope,
    );

    expect($agreeing)->toBe(['type' => 'string[]', 'optional' => false])
        ->and($disagreeing)->toBe(['type' => '(string | number)[]', 'optional' => false]);
});

it('resolves a keyed record constant to an inline object, including nested records', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'DEFAULT_CHANNELS');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope());

    expect($result)->toBe([
        'type' => '{ in_app: { status_updates: boolean; comments: boolean }; '
            .'digest: { status_updates: boolean; comments: boolean } }',
        'optional' => false,
    ]);
});

it('propagates an embedded enum case FQCN out of a nested list/record constant', function () {
    $resolver = new ValueResolver;
    $scope = valueResolverTestScope();

    $listResult = $resolver->resolveClassConstant(
        valueResolverClassConstFetch(ChannelDefaults::class, 'STATUS_LIST'), $scope,
    );
    $recordResult = $resolver->resolveClassConstant(
        valueResolverClassConstFetch(ChannelDefaults::class, 'STATUS_MAP'), $scope,
    );

    expect($listResult['embeddedEnumFqcns'] ?? null)->not->toBeNull()
        ->and($recordResult['embeddedEnumFqcns'] ?? null)->not->toBeNull();
});

// resolveClassConstant() — bails to null past the array limits

it('declines an over-element-count constant array', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'OVER_ELEMENT_LIMIT');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope());

    expect($result)->toBeNull();
});

it('declines an over-depth constant array', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'OVER_DEPTH_LIMIT');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope());

    expect($result)->toBeNull();
});

// resolveClassConstant() — `Foo::class` and enum cases

it('resolves Foo::class to a plain string, not a decline', function () {
    // This is the "New behaviour" pinned in ResourceAstAnalyzerTest's resource_marker case: the four
    // risky call sites never reach this branch (they read resolveClassConstArgument() directly).
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'class');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope());

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('types a bare enum-case fetch as its enum, on the enum import channel', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(Status::class, 'Draft');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope());

    expect($result)->toBe(['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class]);
});

it('types a pure enum case as its enum too, the names a model or a metadata file writes it as', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(Visibility::class, 'Public');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope());

    expect($result)->toBe(['type' => 'VisibilityType', 'optional' => false, 'directEnumFqcn' => Visibility::class]);
});

it('declines an enum case in a scope that carries no import', function () {
    $scope = valueResolverTestScope();
    $scope->carriesImports = false;

    expect(new ValueResolver()->resolveClassConstant(valueResolverClassConstFetch(Status::class, 'Draft'), $scope))->toBeNull();
});

it('declines a constant whose lazily-evaluated initializer throws, instead of aborting', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'BROKEN');

    $result = $resolver->resolveClassConstant($expr, valueResolverTestScope());

    expect($result)->toBeNull();
});

// resolveClassConstArgument() — the public helper analyzeToResourceCall()/analyzeToResourceCollectionCall() reuse

it('resolves a Foo::class argument to its FQCN', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'class');

    expect($resolver->resolveClassConstArgument($expr))->toBe(ChannelDefaults::class);
});

it('declines an enum-case fetch as a ::class argument', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(Status::class, 'Draft');

    expect($resolver->resolveClassConstArgument($expr))->toBeNull();
});

it('declines a plain (non-::class) constant fetch as a ::class argument', function () {
    $resolver = new ValueResolver;
    $expr = valueResolverClassConstFetch(ChannelDefaults::class, 'MAX_RETRIES');

    expect($resolver->resolveClassConstArgument($expr))->toBeNull();
});

// evaluateConstantExpression() and resolveConstantValue() — a parameter default, evaluated as PHP evaluates it

it('evaluates a constant expression as PHP does, reading class constants and enum cases through the subject', function (string $php, mixed $value) {
    expect(new ValueResolver()->evaluateConstantExpression(valueResolverParse($php), valueResolverTestScope()))->toBe($value);
})->with([
    'protected self:: and parent:: constants' => ['[self::SCHEMA_VERSION, parent::BASE_VERSION]', [2, 1]],
    'self::class' => ['self::class', ClassConstantResource::class],
    'an enum case, a spread and an operator' => ['[\\'.Status::class.'::Draft, ...[1 + 1]]', [Status::Draft, 2]],
    'a constant holding an enum case' => ['self::DEFAULT_STATUS', Status::Draft],
    'a ternary' => ['self::SCHEMA_VERSION > 1 ? [1] : "x"', [1]],
    'a global constant' => ['[PHP_INT_SIZE, PHP_EOL]', [PHP_INT_SIZE, PHP_EOL]],
    'an enum case ->name and ->value' => ['[\\'.Status::class.'::Published->name, \\'.Status::class.'::Published->value]', ['Published', 1]],
]);

it('throws for what a constant expression reads that the evaluator cannot', function (string $php) {
    expect(fn () => new ValueResolver()->evaluateConstantExpression(valueResolverParse($php), valueResolverTestScope()))
        ->toThrow(ConstExprEvaluationException::class);
})->with([
    'new' => ['new Foo'],
    'an undefined constant' => ['[NO_SUCH_CONSTANT_ANYWHERE]'],
    'a division by zero' => ['1 / 0'],
    'a constant whose initializer throws' => ['\\'.ChannelDefaults::class.'::BROKEN'],
    'a property of an enum case other than name or value' => ['\\'.Status::class.'::Draft->label'],
    'a magic constant, whose value depends on where it appears' => ['[__TRAIT__ => 1]'],
    'a variable' => ['$other'],
    'a missing class constant' => ['self::MISSING'],
]);

// json_encode() writes a list only when the keys run 0 to n-1; a resource's re-index is its publisher's.
it('types an evaluated value as json_encode() writes it, every key kept as written', function (mixed $value, ?string $type) {
    expect(new ValueResolver()->resolveConstantValue($value)['type'] ?? null)->toBe($type);
})->with([
    'null' => [null, 'null'],
    'a bool' => [false, 'boolean'],
    'a float' => [1.5, 'number'],
    'an empty array' => [[], 'never[]'],
    'an enum case' => [Status::Draft, 'StatusType'],
    'a closure, which a resource sends as {} but Inertia calls' => [static fn (): int => 1, null],
    'an object' => [new stdClass, null],
    'a list' => [[1, 'a'], '(number | string)[]'],
    'int keys that form a list' => [[0 => 'a', 1 => 'b'], 'string[]'],
    'a record with an int key beside a string one' => [['a' => 1, 2], '{ a: number; "0": number }'],
    'int keys that do not form a list' => [[1 => 'a'], '{ "1": string }'],
    'the same, nested in a record' => [['a' => [2 => 'x']], '{ a: { "2": string } }'],
    'float-string keys, which PHP keeps as strings' => [['1.5' => 'x', '-0' => 'y'], '{ "1.5": string; "-0": string }'],
    'an int key beside a float-string one' => [[1 => 'a', '1.5' => 'b'], '{ "1": string; "1.5": string }'],
    'a numeric key beside a word key' => [['1.5' => 1, 'a' => 2], '{ "1.5": number; a: number }'],
]);

it('names the enum an evaluated enum case belongs to, for its import', function () {
    expect(new ValueResolver()->resolveConstantValue(Status::Draft))
        ->toBe(['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class]);
});

it('keeps one element per enum in a constant list of two enums that share a name', function () {
    $resolver = new ValueResolver;

    expect($resolver->resolveConstantValue([Status::Draft, CrmStatus::Lead]))
        ->toBe(['type' => '(StatusType | StatusType)[]', 'optional' => false, 'embeddedEnumFqcns' => [Status::class, CrmStatus::class]])
        ->and($resolver->resolveConstantValue([Status::Draft, Status::Published]))
        ->toBe(['type' => 'StatusType[]', 'optional' => false, 'embeddedEnumFqcns' => [Status::class]]);
});

it('types a new of a class that is not a resource as json_encode() writes it', function (string $class, ?string $type) {
    expect(new ValueResolver()->resolveSerializedNew(new New_(new Name($class)))['type'] ?? null)->toBe($type);
})->with([
    'an exception, which json_encode() writes as {}' => [RuntimeException::class, 'Record<string, never>'],
    'a __toString() class json_encode() writes as {}' => [HtmlString::class, 'Record<string, never>'],
    'a DateTime, which json_encode() writes as an object' => [DateTime::class, LaravelTsPublishService::DATE_TIME_OBJECT_TYPE],
    'a DateTimeImmutable' => [DateTimeImmutable::class, LaravelTsPublishService::DATE_TIME_OBJECT_TYPE],
    'a Carbon date' => [Carbon::class, 'string'],
    'a Stringable, which json_encode() writes as a string' => [Stringable::class, 'string'],
    'an Arrayable whose jsonSerialize() returns a string, which json_encode() prefers' => [StringJsonArrayable::class, 'string'],
    'a ?string jsonSerialize()' => [NullableStringJson::class, 'string | null'],
    'a DateTime whose jsonSerialize() returns an array' => [ArrayJsonDate::class, '{ date: string }'],
    'a Carbon date whose jsonSerialize() override returns an array' => [ArrayJsonCarbon::class, '{ iso: string }'],
    'a plain class with typed public properties' => [Coordinate::class, '{ lat: number; lng: number }'],
    'a Carbon subclass whose mixed jsonSerialize() says string' => [StampedDate::class, 'string'],
    'a model, which a fresh instance writes as no attributes' => [User::class, null],
    'a class json_encode() writes as {} with no __toString()' => [stdClass::class, null],
    'an Arrayable, whose JSON depends on who serializes it' => [ArrayableData::class, null],
    'an Arrayable with a toArray() shape' => [Money::class, null],
    'a Collection, an Arrayable' => [Collection::class, null],
    'a Fluent, an Arrayable' => [Fluent::class, null],
    'a class toTsType() leaves as its token' => [OpaqueHandle::class, null],
    'an Inertia property provider, which Inertia sends as its toInertiaProperty()' => [InertiaPropertyLabel::class, null],
    'a Responsable, which Inertia sends as its toResponse() data' => [ResponsableLabel::class, null],
]);

it('types a Carbon new default as Date under timestamps_as_date, as every other Carbon value', function (string $class) {
    config()->set('ts-publish.timestamps_as_date', true);

    expect(new ValueResolver()->resolveSerializedNew(new New_(new Name($class)))['type'] ?? null)->toBe('Date');
})->with([
    'Illuminate\\Support\\Carbon' => [Carbon::class],
    'Carbon\\Carbon' => [CarbonCarbon::class],
    'Carbon\\CarbonImmutable' => [CarbonImmutable::class],
    'a Carbon subclass whose mixed jsonSerialize() says string' => [StampedDate::class],
]);
