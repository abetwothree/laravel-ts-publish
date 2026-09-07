<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;

afterEach(fn () => AnalysisWarnings::reset());

it('starts empty', function () {
    expect(AnalysisWarnings::all())->toBe([]);
});

it('records a warning with its subject and message', function () {
    AnalysisWarnings::add('App\Http\Controllers\PostController@index', 'TypeError: bad type');

    expect(AnalysisWarnings::all())->toBe([
        ['subject' => 'App\Http\Controllers\PostController@index', 'message' => 'TypeError: bad type'],
    ]);
});

it('accumulates across add() calls', function () {
    AnalysisWarnings::add('Foo@bar', 'first');
    AnalysisWarnings::add('Baz@qux', 'second');

    expect(AnalysisWarnings::all())->toHaveCount(2)
        ->and(AnalysisWarnings::all()[0])->toBe(['subject' => 'Foo@bar', 'message' => 'first'])
        ->and(AnalysisWarnings::all()[1])->toBe(['subject' => 'Baz@qux', 'message' => 'second']);
});

it('returns to empty on reset()', function () {
    AnalysisWarnings::add('Foo@bar', 'first');
    AnalysisWarnings::reset();

    expect(AnalysisWarnings::all())->toBe([]);
});
