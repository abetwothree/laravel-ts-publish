<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MatchHandler;
use PhpParser\Node\Expr;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/**
 * Parse one PHP expression.
 */
function matchHandlerParse(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/**
 * Resolve one expression through the full resource profile over a Post.
 *
 * @return array<string, mixed>
 */
function matchHandlerResolveOnPost(string $php): array
{
    return new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve(matchHandlerParse($php));
}

// `$this->rating` is nullable by itself, so only 'a null arm' shows a literal `null` arm joining the union.
it('types a match as the union of its arms', function (string $php, string $type) {
    expect(matchHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'one type' => ['match ($this->status) { \Workbench\App\Enums\Status::Draft => "draft", default => "live" }', 'string'],
    'two types' => ['match (true) { $this->id > 1 => "many", default => 1 }', 'string | number'],
    'a nullable arm' => ['match (true) { $this->id > 1 => $this->rating, default => null }', 'number | null'],
    'a null arm' => ['match (true) { $this->id > 1 => $this->title, default => null }', 'string | null'],
    'no arm typed' => ['match (true) { $this->id > 1 => json_decode("x"), default => json_decode("y") }', 'unknown'],
]);

// An arm left out beside a typed one is what a ternary does too: the union never widens to `unknown`.
it('leaves out an arm it cannot type and records the drop', function () {
    DroppedUnionArms::start();

    try {
        $type = matchHandlerResolveOnPost('match (true) { $this->id > 1 => json_decode("x"), default => "y" }')['type'];
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    expect($type)->toBe('string')
        ->and(array_column($dropped, 'site'))->toBe(['match-arm'])
        ->and(array_column($dropped, 'expression'))->toBe(['\json_decode("x")']);
});

// A `throw` resolves to `unknown` like an arm the engine failed on, so only skipping it keeps the audit's count honest.
it('skips a throw arm without recording a drop', function () {
    DroppedUnionArms::start();

    try {
        $type = matchHandlerResolveOnPost('match ($this->id) { 1 => "one", default => throw new \RuntimeException("x") }')['type'];
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    expect($type)->toBe('string')
        ->and($dropped)->toBe([]);
});

it('keeps an EnumResource arm on the enum-resource channel', function () {
    expect(matchHandlerResolveOnPost('match (true) { $this->id > 1 => \AbeTwoThree\LaravelTsPublish\EnumResource::make($this->status), default => null }'))
        ->toMatchArray(['type' => 'StatusType | null', 'enumFqcn' => Status::class]);
});

// Through the dispatcher a decline and `unknown` look alike, since no other handler claims a `match`.
it('declines what it cannot union', function (string $php) {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $engine = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class);

    expect(new MatchHandler()->resolve(matchHandlerParse($php), $scope, $engine))->toBeNull();
})->with([
    'a match whose every arm throws' => ['match ($this->id) { 1 => throw new \LogicException("a"), default => throw new \RuntimeException("b") }'],
    'a match with no arm' => ['match ($this->id) {}'],
    'an expression that is not a match' => ['$this->title'],
]);
