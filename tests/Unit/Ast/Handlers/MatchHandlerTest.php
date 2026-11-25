<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators\MatchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MatchEnumShapesResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use PhpParser\Node\Expr;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\EnumCollectionResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;

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

/**
 * Resolve one expression through the full resource profile over a Team, whose accessors hold the enum lists.
 *
 * @return array<string, mixed>
 */
function matchHandlerResolveOnTeam(string $php): array
{
    return new ResourceAstAnalyzer(new ReflectionClass(EnumCollectionResource::class), Team::class)->resolve(matchHandlerParse($php));
}

// `$this->rating` is nullable by itself, so only 'a null arm' shows a literal `null` arm joining the union.
it('types a match as the union of its arms', function (string $php, string $type) {
    expect(matchHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'one type' => ['match ($this->status) { \Workbench\App\Enums\Status::Draft => "draft", default => "live" }', 'string'],
    'two types' => ['match (true) { $this->id > 1 => "many", default => 1 }', 'string | number'],
    'a nullable arm' => ['match (true) { $this->id > 1 => $this->rating, default => null }', 'string | null'],
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

it('keeps an EnumResource arm spelled as its wrap, its const queued', function () {
    expect(matchHandlerResolveOnPost('match (true) { $this->id > 1 => \AbeTwoThree\LaravelTsPublish\EnumResource::make($this->status), default => null }'))
        ->toMatchArray(['type' => 'AsEnum<typeof Status> | null', 'embeddedEnumResourceFqcns' => [Status::class]]);
});

// Each arm of a mixed enum union publishes as it does alone, in arm order, so its own `[]` stays on it.
it('spells each arm of a mixed enum match as its ternary twin does', function (string $match, string $ternary, string $type) {
    $result = matchHandlerResolveOnTeam($match);

    expect($result)->toBe(matchHandlerResolveOnTeam($ternary))
        ->and($result['type'])->toBe($type);
})->with([
    'a collection wrap and a scalar direct read' => [
        'match (true) { $this->is_active => \AbeTwoThree\LaravelTsPublish\EnumResource::collection($this->status_history), default => $this->latest_status }',
        '$this->is_active ? \AbeTwoThree\LaravelTsPublish\EnumResource::collection($this->status_history) : $this->latest_status',
        'AsEnum<typeof Status>[] | StatusType',
    ],
    'a collection wrap and an array direct read' => [
        'match (true) { $this->is_active => \AbeTwoThree\LaravelTsPublish\EnumResource::collection($this->status_history), default => $this->status_history }',
        '$this->is_active ? \AbeTwoThree\LaravelTsPublish\EnumResource::collection($this->status_history) : $this->status_history',
        'AsEnum<typeof Status>[] | StatusType[]',
    ],
]);

// A ternary has two arms and a match any number, so these have no twin: every arm keeps its own spelling.
it('spells every arm of a mixed enum match', function (string $php, string $type) {
    expect(matchHandlerResolveOnTeam($php)['type'])->toBe($type);
})->with([
    'two wraps that agree' => [
        'match ($this->id) { 1 => \AbeTwoThree\LaravelTsPublish\EnumResource::collection($this->status_history), 2 => \AbeTwoThree\LaravelTsPublish\EnumResource::collection($this->status_history), default => $this->latest_status }',
        'AsEnum<typeof Status>[] | StatusType',
    ],
    'two direct reads that agree' => [
        'match ($this->id) { 1 => \AbeTwoThree\LaravelTsPublish\EnumResource::make($this->latest_status), 2 => $this->status_history, default => $this->status_history }',
        'AsEnum<typeof Status> | StatusType[]',
    ],
    'an arm that holds no enum' => [
        'match ($this->id) { 1 => \AbeTwoThree\LaravelTsPublish\EnumResource::collection($this->status_history), 2 => $this->latest_status, default => null }',
        'AsEnum<typeof Status>[] | StatusType | null',
    ],
    'an arm that is itself mixed' => [
        'match ($this->id) { 1 => ($this->is_active ? \AbeTwoThree\LaravelTsPublish\EnumResource::make($this->latest_status) : $this->latest_status), default => $this->latest_status }',
        'AsEnum<typeof Status> | StatusType',
    ],
    'wrap arms that disagree' => [
        'match ($this->id) { 1 => \AbeTwoThree\LaravelTsPublish\EnumResource::make($this->latest_status), 2 => \AbeTwoThree\LaravelTsPublish\EnumResource::collection($this->status_history), default => $this->latest_status }',
        'AsEnum<typeof Status> | AsEnum<typeof Status>[] | StatusType',
    ],
    'direct arms that disagree' => [
        'match ($this->id) { 1 => \AbeTwoThree\LaravelTsPublish\EnumResource::make($this->latest_status), 2 => $this->latest_status, default => $this->status_history }',
        'AsEnum<typeof Status> | StatusType | StatusType[]',
    ],
]);

it('publishes a mixed enum match with the [] on whichever arm is a list, as its ternary twin does', function () {
    config()->set('ts-publish.enums.use_tolki_package', true);

    $properties = (new ResourceTransformer(MatchEnumShapesResource::class))->data()->properties;

    expect($properties['history_or_scalar']['type'])->toBe('AsEnum<typeof Status>[] | StatusType')
        ->and($properties['history_or_array']['type'])->toBe('AsEnum<typeof Status>[] | StatusType[]');
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
