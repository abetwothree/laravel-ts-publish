<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ArrayMergeHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ConstantKeyResource;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;

/**
 * A real analyzer as the engine: the handler re-expresses its arguments as one array literal and
 * hands that back to the engine, so only a live dispatcher exercises the whole path.
 */
function arrayMergeTestEngine(): ResourceAstAnalyzer
{
    return new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);
}

/** @param  list<Arg|VariadicPlaceholder>  $args */
function arrayMergeCall(array $args): FuncCall
{
    return new FuncCall(new Name('array_merge'), $args);
}

function arrayMergeLiteral(string $key, int|string $value): Arg
{
    $node = is_int($value) ? new Int_($value) : new String_($value);

    return new Arg(new Array_([new ArrayItem($node, new String_($key))]));
}

it('merges array literal arguments into one inline object', function () {
    $expr = arrayMergeCall([arrayMergeLiteral('id', 1), arrayMergeLiteral('name', 'Ada')]);

    expect((new ArrayMergeHandler)->resolve($expr, new AnalysisScope(new ReflectionClass(stdClass::class)), arrayMergeTestEngine()))
        ->toBe(['type' => '{ id: number; name: string }', 'optional' => false, 'keepsKeys' => true]);
});

it('lets a later argument overwrite an earlier key, keeping its position', function () {
    $expr = arrayMergeCall([
        arrayMergeLiteral('id', 1),
        arrayMergeLiteral('name', 'Ada'),
        arrayMergeLiteral('id', 'first'),
    ]);

    // PHP's array_merge keeps the first occurrence's position and the last occurrence's value.
    expect((new ArrayMergeHandler)->resolve($expr, new AnalysisScope(new ReflectionClass(stdClass::class)), arrayMergeTestEngine())['type'])
        ->toBe('{ id: string; name: string }');
});

// array_merge() renumbers every int key from 0 across its arguments, an int-like string's included.
it('renumbers each int key as array_merge() does', function (string $php, string $type) {
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;

    expect((new ArrayMergeHandler)->resolve($expr, new AnalysisScope(new ReflectionClass(stdClass::class)), arrayMergeTestEngine())['type'])
        ->toBe($type);
})->with([
    'an int key beside a named one' => ['array_merge(["a" => 1], [42 => "x"])', '{ a: number; "0": string }'],
    'the same int key in two arguments' => ['array_merge([0 => "a"], [0 => 1])', '(string | number)[]'],
    'an int-like string key' => ['array_merge(["a" => 1], ["7" => "x"])', '{ a: number; "0": string }'],
    'a numeric string PHP keeps as a string' => ['array_merge(["a" => 1], ["06" => "x"])', '{ a: number; "06": string }'],
    'an int key after a keyless item' => ['array_merge(["x"], [9 => "y", "b" => 2])', '{ "0": string; "1": string; b: number }'],
    'keyless items and int keys that renumber into a run from 0' => ['array_merge(["x"], [9 => "y"])', 'string[]'],
    'a keyless item before a named key' => ['array_merge(["a" => 1], ["x"])', '{ a: number; "0": string }'],
]);

// A constant key is stored as its value: an int, or an int-like string, renumbers; any other string stays a named key.
it('renumbers a class-constant key holding an int as array_merge() does', function (string $php, string $type) {
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    $subject = new ReflectionClass(ConstantKeyResource::class);

    expect((new ArrayMergeHandler)->resolve($expr, new AnalysisScope($subject), new ResourceAstAnalyzer($subject))['type'])
        ->toBe($type);
})->with([
    'an int constant' => ['array_merge(["a" => 1], [self::NUMERIC_KEY => "x", 5 => "y"])', '{ a: number; "0": string; "1": string }'],
    'an int-like string constant' => ['array_merge(["a" => 1], [self::NUMERIC_TEXT_KEY => "x", 5 => "y"])', '{ a: number; "0": string; "1": string }'],
    'a string constant' => ['array_merge(["a" => 1], [self::EXTERNAL_KEY => "x", 5 => "y"])', '{ a: number; external: string; "0": string }'],
    'an unreadable constant, which stops the count' => ['array_merge(["a" => 1], [self::MISSING => "x", 5 => "y"])', '{ a: number; "5": string }'],
]);

it('declines when an argument is not an array literal or a parent call', function () {
    $expr = arrayMergeCall([arrayMergeLiteral('id', 1), new Arg(new Variable('extra'))]);

    expect((new ArrayMergeHandler)->resolve($expr, new AnalysisScope(new ReflectionClass(stdClass::class)), arrayMergeTestEngine()))
        ->toBeNull();
});

it('folds a parent:: argument in, and declines the same shape on any other class', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $parent = arrayMergeCall([
        new Arg(new StaticCall(new Name('parent'), 'toArray', [new Arg(new Variable('request'))])),
        arrayMergeLiteral('extra', 1),
    ]);
    $foreign = arrayMergeCall([
        new Arg(new StaticCall(new Name('Other'), 'toArray', [new Arg(new Variable('request'))])),
        arrayMergeLiteral('extra', 1),
    ]);

    // CommentResource's parent is JsonResource, so parent::toArray() resolves to the Comment model
    // dump — `content` can only have arrived through the parent argument, never from the literal.
    expect((new ArrayMergeHandler)->resolve($parent, $scope, arrayMergeTestEngine())['type'])
        ->toContain('content: string')
        ->toContain('extra: number')
        ->and((new ArrayMergeHandler)->resolve($foreign, $scope, arrayMergeTestEngine()))
        ->toBeNull();
});

it('declines another function and a first-class callable', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $engine = arrayMergeTestEngine();

    expect((new ArrayMergeHandler)->resolve(new FuncCall(new Name('array_filter'), [arrayMergeLiteral('id', 1)]), $scope, $engine))
        ->toBeNull()
        ->and((new ArrayMergeHandler)->resolve(arrayMergeCall([new VariadicPlaceholder]), $scope, $engine))
        ->toBeNull();
});
