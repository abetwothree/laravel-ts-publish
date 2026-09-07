<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use AbeTwoThree\LaravelTsPublish\Support\JsEmitter as JsEmitterService;
use AbeTwoThree\LaravelTsPublish\Support\TsNaming as TsNamingService;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressResource;

test('LaravelTsPublish still answers every JsEmitter helper, byte-equal to JsEmitter', function () {
    $routeArgs = [
        ['name' => 'id', 'required' => true, 'where' => '[0-9]+'],
    ];

    expect(LaravelTsPublish::validJsObjectKey('foo-bar'))->toBe(JsEmitter::validJsObjectKey('foo-bar'))
        ->and(LaravelTsPublish::validJsObjectKey('[key: number]', allowIndexSignature: true))
        ->toBe(JsEmitter::validJsObjectKey('[key: number]', allowIndexSignature: true))
        ->and(LaravelTsPublish::safeJsIdentifier('class', 'Route'))->toBe(JsEmitter::safeJsIdentifier('class', 'Route'))
        ->and(LaravelTsPublish::toJsLiteral(['a' => 1, 'b' => null]))->toBe(JsEmitter::toJsLiteral(['a' => 1, 'b' => null]))
        ->and(LaravelTsPublish::enumScalar(Status::Published))->toBe(JsEmitter::enumScalar(Status::Published))
        ->and(LaravelTsPublish::routeArgsToJs($routeArgs))->toBe(JsEmitter::routeArgsToJs($routeArgs))
        ->and(LaravelTsPublish::sanitizeJsDoc('a */ b'))->toBe(JsEmitter::sanitizeJsDoc('a */ b'))
        ->and(LaravelTsPublish::formatJsDoc("Line one\nLine two", 4))->toBe(JsEmitter::formatJsDoc("Line one\nLine two", 4))
        ->and(LaravelTsPublish::parseDocBlockDescription("/**\n * Hello.\n */"))->toBe(JsEmitter::parseDocBlockDescription("/**\n * Hello.\n */"));
});

// Guards the assertions above: an input a helper passes straight through would pin nothing.
test('every delegation input is one the helper actually transforms', function () {
    expect(JsEmitter::validJsObjectKey('foo-bar'))->toBe('"foo-bar"')
        ->and(JsEmitter::validJsObjectKey('[key: number]', allowIndexSignature: true))->toBe('[key: number]')
        ->and(JsEmitter::validJsObjectKey('[key: number]'))->toBe('"[key: number]"')
        ->and(JsEmitter::safeJsIdentifier('class', 'Route'))->toBe('classRoute')
        ->and(JsEmitter::toJsLiteral(['a' => 1, 'b' => null]))->toBe('{a: 1, b: null}')
        ->and(JsEmitter::enumScalar(Status::Published))->toBe(1)
        ->and(JsEmitter::routeArgsToJs([['name' => 'id', 'required' => true, 'where' => '[0-9]+']]))
        ->toBe("[{name: 'id', required: true, where: '[0-9]+'}]")
        ->and(JsEmitter::sanitizeJsDoc('a */ b'))->toBe('a *\/ b')
        ->and(JsEmitter::formatJsDoc("Line one\nLine two", 4))->toBe("    /**\n     * Line one\n     * Line two\n     */")
        ->and(JsEmitter::parseDocBlockDescription("/**\n * Hello.\n */"))->toBe('Hello.');
});

test('LaravelTsPublish still answers every TsTypeString helper, byte-equal to TsTypeString', function () {
    $itemFqcns = ['App\\Models\\User', 'Crm\\Models\\User'];
    $nameMap = ['App\\Models\\User' => 'User', 'Crm\\Models\\User' => 'User'];
    $aliases = ['App\\Models\\User' => 'AppUser', 'Crm\\Models\\User' => 'CrmUser'];
    $namespacedTypes = ['crm.models' => ['User'], 'app.models' => ['Post']];
    $aliasResolution = ['CrmUser' => 'crm.models.User'];
    $constToTypeMap = ['StatusEnum' => 'enums.Status'];

    // A facade cannot carry a constant, so this one alias is pinned on the concrete classes.
    expect(LaravelTsPublishService::TS_PRIMITIVES)->toBe(TsTypeStringService::TS_PRIMITIVES)
        ->and(LaravelTsPublish::shapeValueHasUnimportableToken('{ owner: User }', ['User']))
        ->toBe(TsTypeString::shapeValueHasUnimportableToken('{ owner: User }', ['User']))
        ->and(LaravelTsPublish::shapeValueHasUnimportableToken('{ owner: User }'))
        ->toBe(TsTypeString::shapeValueHasUnimportableToken('{ owner: User }'))
        ->and(LaravelTsPublish::extractImportableTypes('User | string | Post[]'))
        ->toBe(TsTypeString::extractImportableTypes('User | string | Post[]'))
        ->and(LaravelTsPublish::aliasPropertyType('User | User', $itemFqcns, $nameMap, $aliases))
        ->toBe(TsTypeString::aliasPropertyType('User | User', $itemFqcns, $nameMap, $aliases))
        ->and(LaravelTsPublish::qualifyGlobalType('CrmUser | Post', $namespacedTypes, 'crm.models', $aliasResolution))
        ->toBe(TsTypeString::qualifyGlobalType('CrmUser | Post', $namespacedTypes, 'crm.models', $aliasResolution))
        ->and(LaravelTsPublish::splitTopLevelUnion('{ a: string | null } | null'))
        ->toBe(TsTypeString::splitTopLevelUnion('{ a: string | null } | null'))
        ->and(LaravelTsPublish::hoistNull(['string | null', 'number | null']))
        ->toBe(TsTypeString::hoistNull(['string | null', 'number | null']))
        ->and(LaravelTsPublish::typeNameOccursIn('StatusType', 'StatusType | null'))
        ->toBe(TsTypeString::typeNameOccursIn('StatusType', 'StatusType | null'))
        ->and(LaravelTsPublish::substituteEnumType('Role | null', 'Role', 'AsEnum<typeof RoleEnum>'))
        ->toBe(TsTypeString::substituteEnumType('Role | null', 'Role', 'AsEnum<typeof RoleEnum>'))
        ->and(LaravelTsPublish::rewriteAsEnumToType('AsEnum<typeof StatusEnum> | Status', $constToTypeMap))
        ->toBe(TsTypeString::rewriteAsEnumToType('AsEnum<typeof StatusEnum> | Status', $constToTypeMap))
        ->and(LaravelTsPublish::isVagueTsType('unknown'))->toBe(TsTypeString::isVagueTsType('unknown'))
        ->and(LaravelTsPublish::isVagueTsType('{ a: unknown }'))->toBe(TsTypeString::isVagueTsType('{ a: unknown }'));
});

// Guards the assertions above, and pins the defaulted parameters a delegation could silently drop:
// $importableNames on shapeValueHasUnimportableToken, $skipNamespace and $aliasResolution on qualifyGlobalType.
test('every TsTypeString delegation input is one the helper actually transforms', function () {
    $namespacedTypes = ['crm.models' => ['User'], 'app.models' => ['Post']];
    $aliasResolution = ['CrmUser' => 'crm.models.User'];

    expect(TsTypeString::shapeValueHasUnimportableToken('{ owner: User }', ['User']))->toBeFalse()
        ->and(TsTypeString::shapeValueHasUnimportableToken('{ owner: User }'))->toBeTrue()
        ->and(TsTypeString::extractImportableTypes('User | string | Post[]'))->toBe(['User', 'Post'])
        ->and(TsTypeString::aliasPropertyType(
            'User | User',
            ['App\\Models\\User', 'Crm\\Models\\User'],
            ['App\\Models\\User' => 'User', 'Crm\\Models\\User' => 'User'],
            ['App\\Models\\User' => 'AppUser', 'Crm\\Models\\User' => 'CrmUser'],
        ))->toBe('AppUser | CrmUser')
        ->and(TsTypeString::qualifyGlobalType('CrmUser | Post', $namespacedTypes, 'crm.models', $aliasResolution))
        ->toBe('User | app.models.Post')
        ->and(TsTypeString::qualifyGlobalType('CrmUser | Post', $namespacedTypes, '', $aliasResolution))
        ->toBe('crm.models.User | app.models.Post')
        ->and(TsTypeString::qualifyGlobalType('CrmUser | Post', $namespacedTypes, 'crm.models'))
        ->toBe('CrmUser | app.models.Post')
        ->and(TsTypeString::splitTopLevelUnion('{ a: string | null } | null'))
        ->toBe(['{ a: string | null }', 'null'])
        ->and(TsTypeString::hoistNull(['string | null', 'number | null']))->toBe('string | number | null')
        ->and(TsTypeString::typeNameOccursIn('StatusType', 'StatusType | null'))->toBeTrue()
        ->and(TsTypeString::typeNameOccursIn('StatusType', 'foo.StatusType'))->toBeFalse()
        ->and(TsTypeString::substituteEnumType('Role | null', 'Role', 'AsEnum<typeof RoleEnum>'))
        ->toBe('AsEnum<typeof RoleEnum> | null')
        ->and(TsTypeString::rewriteAsEnumToType('AsEnum<typeof StatusEnum> | Status', ['StatusEnum' => 'enums.Status']))
        ->toBe('enums.Status')
        ->and(TsTypeString::isVagueTsType('object'))->toBeTrue()
        ->and(TsTypeString::isVagueTsType('unknown'))->toBeTrue()
        ->and(TsTypeString::isVagueTsType('{ a: unknown }'))->toBeFalse()
        ->and(TsTypeString::isVagueTsType('string'))->toBeFalse();
});

test('LaravelTsPublish still answers every TsNaming helper, byte-equal to TsNaming', function () {
    $imports = ['../enums' => ['Status'], 'luxon' => ['DateTime'], './types' => ['UserType']];
    $insidePath = base_path('src/Nested/Thing.php');
    $statusFile = workbench_path('app/Enums/Status.php');

    // CoreTransformer and WatcherJsonWriter call resolveRelativePath statically on the concrete
    // class, so the static form is pinned here alongside the facade form.
    expect(LaravelTsPublishService::resolveRelativePath($insidePath))->toBe(TsNaming::resolveRelativePath($insidePath))
        ->and(LaravelTsPublishService::resolveRelativePath(__FILE__))->toBe(TsNaming::resolveRelativePath(__FILE__))
        ->and(LaravelTsPublish::resolveRelativePath($insidePath))->toBe(TsNaming::resolveRelativePath($insidePath))
        ->and(LaravelTsPublish::keyCase('some_relation', 'camel'))->toBe(TsNaming::keyCase('some_relation', 'camel'))
        ->and(LaravelTsPublish::keyCase('some_relation', 'pascal'))->toBe(TsNaming::keyCase('some_relation', 'pascal'))
        ->and(LaravelTsPublish::resourceTypeName(AddressResource::class))
        ->toBe(TsNaming::resourceTypeName(AddressResource::class))
        ->and(LaravelTsPublish::namespaceToPath('App\\UserSettings\\AccountPreference'))
        ->toBe(TsNaming::namespaceToPath('App\\UserSettings\\AccountPreference'))
        ->and(LaravelTsPublish::relativeImportPath('app/domain/billing/models', 'shipping/enums'))
        ->toBe(TsNaming::relativeImportPath('app/domain/billing/models', 'shipping/enums'))
        ->and(LaravelTsPublish::sortImportPaths($imports))->toBe(TsNaming::sortImportPaths($imports))
        ->and(LaravelTsPublish::resolveClassFromFile($statusFile))->toBe(TsNaming::resolveClassFromFile($statusFile));
});

// Guards the assertions above, and pins the second parameter of each two-argument helper: keyCase's
// $case, and relativeImportPath's $toNamespacePath, whose order a delegation could silently swap.
test('every TsNaming delegation input is one the helper actually transforms', function () {
    $insidePath = base_path('src/Nested/Thing.php');

    expect(TsNaming::resolveRelativePath($insidePath))->toBe('src/Nested/Thing.php')
        ->and(TsNaming::keyCase('some_relation', 'camel'))->toBe('someRelation')
        ->and(TsNaming::keyCase('some_relation', 'pascal'))->toBe('SomeRelation')
        ->and(TsNaming::resourceTypeName(AddressResource::class))->toBe('Address')
        ->and(TsNaming::namespaceToPath('App\\UserSettings\\AccountPreference'))->toBe('app/user-settings')
        ->and(TsNaming::relativeImportPath('app/domain/billing/models', 'shipping/enums'))
        ->toBe('../../../../shipping/enums')
        ->and(TsNaming::relativeImportPath('shipping/enums', 'app/domain/billing/models'))
        ->toBe('../../app/domain/billing/models')
        ->and(array_keys(TsNaming::sortImportPaths(['../enums' => ['Status'], 'luxon' => ['DateTime'], './types' => ['UserType']])))
        ->toBe(['luxon', '../enums', './types'])
        ->and(TsNaming::resolveClassFromFile(workbench_path('app/Enums/Status.php')))
        ->toBe('Workbench\\App\\Enums\\Status');
});

// Every delegation reaches these helpers through their facade, which memoises the instance itself, so
// the container bindings change nothing observable today. They stop being decoration the moment a
// caller is constructor-injected: TsNaming's $resourceTypeNames cache would then never warm.
test('each extracted helper is bound as one shared instance', function () {
    expect(app(JsEmitterService::class))->toBe(app(JsEmitterService::class))
        ->and(app(TsTypeStringService::class))->toBe(app(TsTypeStringService::class))
        ->and(app(TsNamingService::class))->toBe(app(TsNamingService::class));
});
