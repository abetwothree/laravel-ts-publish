<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Workbench\App\Enums\Status;

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
