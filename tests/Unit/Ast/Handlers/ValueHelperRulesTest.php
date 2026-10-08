<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/** The type one expression resolves to through the resource profile of PostResource over a Post. */
function valueHelperRuleType(string $php): string
{
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;

    return new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($expr)['type'];
}

it('types the value helpers as json_encode() writes what they return', function (string $php, string $type) {
    expect(valueHelperRuleType($php))->toBe($type);
})->with([
    'now()' => ['now()', 'string'],
    'now() with a timezone' => ['now("UTC")', 'string'],
    'today()' => ['today()', 'string'],
    'str() with a string' => ['str("abc")', 'string'],
    'str() with null, still a Stringable' => ['str(null)', 'string'],
    'str() with no argument, an object with no public property' => ['str()', 'Record<string, never>'],
    'url() with a path' => ['url("/x")', 'string'],
    'url() with a column' => ['url($this->title)', 'string'],
    'collect() with nothing' => ['collect()', 'never[]'],
    'collect() with null' => ['collect(null)', 'never[]'],
    'collect() with an empty list' => ['collect([])', 'never[]'],
    'collect() with a list' => ['collect([1, 2])', 'number[]'],
    'collect() with a mixed list' => ['collect([1, "a"])', '(number | string)[]'],
    'collect() with a nested list' => ['collect([[1, 2], [3]])', 'number[][]'],
    'collect() with a record' => ['collect(["a" => 1])', '{ a: number }'],
    'collect() with a list of columns' => ['collect([$this->title, $this->content])', 'string[]'],
    'collect() with a record of columns' => ['collect(["title" => $this->title])', '{ title: string }'],
    'collect() with a list-typed expression' => ['collect(explode(",", $this->title))', 'string[]'],
]);

it('leaves the value helpers it cannot type, and __(), to unknown', function (string $php) {
    expect(valueHelperRuleType($php))->toBe('unknown');
})->with([
    'url() with no path returns the UrlGenerator' => ['url()'],
    'url() with null returns the UrlGenerator' => ['url(null)'],
    'collect() of a scalar' => ['collect("abc")'],
    'collect() of a list holding an untypable element' => ['collect([$this->title, json_decode("{}")])'],
    'collect() of a record a resource re-indexes' => ['collect([1 => "a"])'],
    '__() can return an array' => ['__("x")'],
    'collect() of a spread, whose arguments are unknown' => ['collect(...$this->tags)'],
    'str() of a spread' => ['str(...[$this->title])'],
]);

it('types a when() default helper value, which the key used to drop', function () {
    expect(valueHelperRuleType('$this->when($this->title, 1, now())'))->toBe('number | string')
        ->and(valueHelperRuleType('$this->when($this->title, 1, collect([1, 2]))'))->toBe('number | number[]');
});

it('types now() as Date under timestamps_as_date', function () {
    config()->set('ts-publish.timestamps_as_date', true);

    expect(valueHelperRuleType('now()'))->toBe('Date');
});
