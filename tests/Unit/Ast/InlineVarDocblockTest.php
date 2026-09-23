<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsInstanceofGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\DeclaredTotalsTraitResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use Workbench\App\Http\Resources\CartTotalsResource;
use Workbench\App\Http\Resources\DeclaredConditionalResource;
use Workbench\App\Http\Resources\DeclaredPrecedenceResource;
use Workbench\App\Http\Resources\DeclaredReadingResource;
use Workbench\App\Http\Resources\PostPinnedCommentsResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\CartTotals;

/**
 * Parse a method body and seed a Post-backed resource scope from it, as the analyzer seeds `toArray()`.
 *
 * @return array{AnalysisScope, list<Stmt>}
 */
function inlineVarSeededBody(string $body): array
{
    $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
    /** @var list<Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);

    $host = new class
    {
        use CollectsInstanceofGuards;
        use CollectsLocalVarBindings;

        /**
         * Seed the scope from the body, as the analyzer does.
         *
         * @param  list<Stmt>  $stmts
         */
        public function run(array $stmts, AnalysisScope $scope): void
        {
            $this->collectLocalVarBindings($stmts, $scope);
            $this->collectInstanceofGuards($stmts, $scope);
        }
    };

    $host->run($stmts, $scope);

    return [$scope, $stmts];
}

/**
 * The classes each `$x->member` read in a body resolves its receiver to, in source order; null where none is named.
 *
 * @return list<list<class-string>|null>
 */
function inlineVarReadClasses(string $body): array
{
    [$scope, $stmts] = inlineVarSeededBody($body);
    $reads = new NodeFinder()->find($stmts, fn (Node $node): bool => $node instanceof PropertyFetch
        && $node->var instanceof Variable
        && $node->var->name === 'x');

    return array_map(
        fn (Node $read): ?array => $read instanceof PropertyFetch
            ? resolve(ReceiverClassResolver::class)->resolve($read->var, $scope)?->classes
            : null,
        $reads,
    );
}

/**
 * The expression a seeded body's final `return` hands back, with the scope that body seeded.
 *
 * @return array{AnalysisScope, Expr}
 */
function inlineVarReturned(string $body): array
{
    [$scope, $stmts] = inlineVarSeededBody($body);
    $last = $stmts[array_key_last($stmts)];

    return [$scope, $last instanceof Return_ && $last->expr instanceof Expr ? $last->expr : throw new LogicException('No return.')];
}

describe('an inline @var on a local assignment', function () {
    test('types every read through the local after it, and leaves the controls to the assignment', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => CartTotalsResource::class])->content)->toContain(<<<'TS'
            export interface CartTotalsResource
            {
                subtotal: number;
                chargeable: boolean;
                count: number;
                note: string | null;
                totals: { subtotal: number; chargeable: boolean; count: number; hasExtras: boolean };
                unnamed_count: number;
                label: string;
                count_before: number;
                count_after: unknown;
                missing_count: unknown;
            }
            TS);
    });

    test('names the collection a relation loaded under a name the model does not declare holds', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => PostPinnedCommentsResource::class])->content)
            ->toContain("import type { Comment } from '../../models';")
            ->toContain(<<<'TS'
                export interface PostPinnedCommentsResource
                {
                    id: number;
                    pinned_count: number;
                    pinned: Comment[];
                }
                TS);
    });

    test('resolves its names against the file of the body it sits in, a trait\'s included', function () {
        $props = collect(resolve(AstEngine::class)->analyze(DeclaredTotalsTraitResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toBe(['trait_count' => 'number']);
    });

    test('holds for the reads after its statement and before the statement that next writes the variable', function (string $body, array $classes) {
        expect(inlineVarReadClasses($body))->toBe($classes);
    })->with([
        'a named tag, read before and after' => [
            '$x->a; /** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->b; $x->c;',
            [null, [CartTotals::class], [CartTotals::class]],
        ],
        'a name-less tag' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals */ $x = json_decode(""); $x->a;',
            [[CartTotals::class]],
        ],
        'a tag naming another variable, then one naming this one' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $y */ $x = json_decode(""); $x->a; /** @var \Workbench\App\Models\User $x */ $x = json_decode(""); $x->b;',
            [null, [User::class]],
        ],
        'a later write' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a; $x = null; $x->b;',
            [[CartTotals::class], null],
        ],
        'a later write inside a loop, which ends the span where the loop starts' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a; foreach ([1] as $i) { $x->b; $x = null; } $x->c;',
            [[CartTotals::class], null, null],
        ],
        'a by-reference closure use after it' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a; $f = function () use (&$x) {}; $x->b;',
            [[CartTotals::class], null],
        ],
        'a write before it' => [
            '$x = null; /** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a;',
            [[CartTotals::class]],
        ],
        'a second annotated assignment, which starts a span of its own' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = json_decode(""); $x->a; /** @var \Workbench\App\Models\User $x */ $x = json_decode(""); $x->b;',
            [[CartTotals::class], [User::class]],
        ],
        'a second write inside the assigning statement, then a plain annotated assignment' => [
            '/** @var \Workbench\App\ValueObjects\CartTotals $x */ $x = ($x = null) ?? json_decode(""); $x->a; /** @var \Workbench\App\Models\User $x */ $x = json_decode(""); $x->b;',
            [null, [User::class]],
        ],
    ]);

    test('keeps what the assignment reads when it names a class, and a union or ?T names each class it does not', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        [$modelScope, $model] = inlineVarReturned('/** @var \Illuminate\Database\Eloquent\Model $a */ $a = $this->author; return $a;');
        [$collectionScope, $collection] = inlineVarReturned('/** @var \Illuminate\Database\Eloquent\Collection<int, \Workbench\App\Models\Comment> $c */ $c = $this->comments; return $c;');
        [$narrowerScope, $narrower] = inlineVarReturned('/** @var \Workbench\App\Models\SubscribedTeam $t */ $t = new \Workbench\App\Models\Team; return $t;');
        [$unionScope, $union] = inlineVarReturned('/** @var \Workbench\App\Models\Post|\Workbench\App\Models\User|null $u */ $u = json_decode(""); return $u;');
        [$nullableScope, $nullable] = inlineVarReturned('/** @var ?\Workbench\App\Models\Post $p */ $p = json_decode(""); return $p;');

        expect($resolver->resolve($model, $modelScope)?->classes)->toBe([User::class])
            ->and($resolver->resolve($collection, $collectionScope)?->classes)->toBe([EloquentCollection::class])
            ->and($resolver->resolve($collection, $collectionScope)?->elementModel)->toBe(Comment::class)
            ->and($resolver->resolve($narrower, $narrowerScope)?->classes)->toBe([Team::class])
            ->and($resolver->resolve($union, $unionScope)?->classes)->toBe([Post::class, User::class])
            ->and($resolver->resolve($nullable, $nullableScope)?->classes)->toBe([Post::class]);
    });

    test('leaves the receiver to the assignment when the type contradicts it or names no loadable class', function () {
        $resolver = resolve(ReceiverClassResolver::class);
        [$declaredScope, $declared] = inlineVarReturned('/** @var \Workbench\App\ValueObjects\CartTotals $n */ $n = $this->author; return $n;');
        [$scalarScope, $scalar] = inlineVarReturned('/** @var int $n */ $n = $this->author; return $n;');
        [$missingScope, $missing] = inlineVarReturned('/** @var \No\Such\Totals $n */ $n = $this->author; return $n;');

        expect($resolver->resolve($declared, $declaredScope)?->classes)->toBe([User::class])
            ->and($resolver->resolve($scalar, $scalarScope)?->classes)->toBe([User::class])
            ->and($resolver->resolve($missing, $missingScope)?->classes)->toBe([User::class]);
    });

    test('types a bare read, unless the type is too vague to publish or names a model with no file', function () {
        $resolved = function (string $body): string {
            [$scope, $returned] = inlineVarReturned($body);

            return new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class, 'toArray', null, $scope)
                ->resolve($returned)['type'];
        };

        expect($resolved('/** @var list<string> $tags */ $tags = json_decode(""); return $tags;'))->toBe('string[]')
            ->and($resolved('/** @var string $s */ $s = json_decode(""); return $s;'))->toBe('string')
            ->and($resolved('/** @var \Illuminate\Database\Eloquent\Collection<int, \Workbench\App\Models\Comment> $c */ $c = json_decode(""); return $c;'))
            ->toBe('Comment[]')
            ->and($resolved('/** @var array<string, mixed> $row */ $row = ["a" => 1]; return $row;'))->toBe('{ a: number }')
            ->and($resolved('/** @var \Illuminate\Database\Eloquent\Model $m */ $m = $this->author; return $m;'))->toBe('User');
    });

    test('types a member read inside a whenLoaded closure from the declaration, not the loaded relation', function () {
        $closure = new AstParser()->parseSource('<?php $this->whenLoaded("author", function () {
            /** @var \Workbench\App\Models\Comment $c */
            $c = json_decode("");

            return $c->content;
        });')[0]->expr;

        expect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class)->resolve($closure)['type'])
            ->toBe('string');
    });
    test('keeps a reading the declaration admits, and the loaded relation\'s model a vague declaration admits', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => DeclaredReadingResource::class])->content)
            ->toContain("import type { User } from '../../models';")
            ->toContain(<<<'TS'
                export interface DeclaredReadingResource
                {
                    title: string;
                    counts: { a: number; b: number };
                    mixed: { a: number; b: string };
                    shape: { a: number; b: string };
                    id: number;
                    comment_count: number;
                    either: string;
                    scalar: string;
                    author: User;
                    opaque_name?: string;
                }
                TS);
    });

    test('keeps a known reading it admits or contradicts, and fills one that is unknown or vaguer', function () {
        $resolved = function (string $body): string {
            [$scope, $returned] = inlineVarReturned($body);

            return new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class, 'toArray', null, $scope)
                ->resolve($returned)['type'];
        };

        expect($resolved('/** @var string|null $t */ $t = $this->resource->title; return $t;'))->toBe('string')
            ->and($resolved('/** @var array<string, int> $r */ $r = ["a" => 1, "b" => 2]; return $r;'))->toBe('{ a: number; b: number }')
            ->and($resolved('/** @var array{a: int, b?: string} $s */ $s = ["a" => 1, "b" => "x"]; return $s;'))
            ->toBe('{ a: number; b: string }')
            ->and($resolved('/** @var \Workbench\App\Models\User|null $u */ $u = $this->resource->author; return $u;'))->toBe('User')
            ->and($resolved('/** @var list<string> $l */ $l = json_decode(""); return $l;'))->toBe('string[]')
            ->and($resolved('/** @var array{a: int} $v */ $v = (array) json_decode(""); return $v;'))->toBe('{ a: number }')
            ->and($resolved('/** @var int $n */ $n = $this->resource->title; return $n;'))->toBe('string');
    });

    test('declines a tag holding any form outside the ones the docblock resolution reads in full', function (string $type) {
        [$scope, $returned] = inlineVarReturned('/** @var '.$type.' $d */ $d = json_decode(""); return $d;');

        expect(resolve(PropertyDocblockTypeReader::class)->extractVarTag('/** @var '.$type.' $d */', new ReflectionClass(DeclaredPrecedenceResource::class)))
            ->toBeNull()
            ->and($scope->varDocBindings)->toBe([])
            ->and(new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class, 'toArray', null, $scope)
                ->resolve($returned)['type'])->toBe('unknown');
    })->with([
        'a callable signature' => ['callable(int): string'],
        'a callable signature with no space' => ['callable(int):string'],
        'a void callable' => ['callable():void'],
        'a pure callable' => ['pure-callable(int): string'],
        'a callable in a shape' => ['array{fn: callable(int): string}'],
        'a callable in a list' => ['list<callable(int): string>'],
        'a callable in a record' => ['array<string, callable(int): string>'],
        'a closure signature' => ['\Closure(int): string'],
        'an intersection' => ['\Workbench\App\Models\Post&\JsonSerializable'],
        'an unclosed shape' => ['array{a: int'],
        'a tuple list' => ['list{int, string}'],
        'an object shape' => ['object{a: int}'],
        'literal keys' => ["array<'a'|'b', int>"],
        'a quoted shape key, which the shape reader skips' => ["array{'a': int, b: string}"],
        'a positional shape' => ['array{int, string}'],
        'an empty shape' => ['array{}'],
        'a bracket list' => ['int[]'],
        'a parenthesized union' => ['(int|string)[]'],
        'a key union' => ['array<int|string, int>'],
        'an array-key key' => ['array<array-key, int>'],
        'a one-parameter array' => ['array<int>'],
        'a one-parameter collection' => ['\Illuminate\Support\Collection<\Workbench\App\Models\Comment>'],
        'a generic that is not a collection' => ['\Workbench\App\Models\Post<int, int>'],
        'a pseudo-type' => ['non-empty-string'],
        'an integer range' => ['int<0, max>'],
        'integer literals' => ['1|2|3'],
        'a class string' => ['class-string<\Workbench\App\Models\Post>'],
        'a bare array' => ['array'],
        'mixed' => ['mixed'],
        'object' => ['object'],
        'scalar' => ['scalar'],
        'a missing class' => ['\No\Such\Totals'],
        'a global class the file does not import' => ['Exception'],
        'a nullable union' => ['?int|string'],
        'a nullable member' => ['int|?string'],
        'a dangling union' => ['string|'],
        'a nullable shape inside a shape' => ['array{a: ?array{b: int}}'],
        'a nullable shape inside a listed shape' => ['list<array{id: int, meta: ?array{k: string}}>'],
        'a nullable shape inside a multi-line shape' => ["array{\n *     a: int,\n *     b: ?array{c: int},\n * }"],
        'a nullable shape' => ['?array{a: int}'],
        'a nullable list' => ['?list<int>'],
        'a nullable keyed array' => ['?array<string, int>'],
        'a list of nullable shapes' => ['list<?array{a: int}>'],
        'a nullable collection' => ['?\Illuminate\Database\Eloquent\Collection<string, User>'],
        'a nullable collection of nullables, whose own null the resolution drops' => ['?\Illuminate\Support\Collection<int, ?Comment>'],
        'a nullable class whose own reading holds null, which the resolution drops' => ['?\Workbench\App\ValueObjects\ArrayableData'],
        'a class bare and in a list before it' => ['list<User>|User'],
        'a class bare and in a list after it' => ['User|list<User>'],
        'a class bare and in a collection' => ['\Illuminate\Support\Collection<int, Comment>|Comment'],
        'a class bare and in a collection, or null' => ['\Illuminate\Support\Collection<int, Comment>|Comment|null'],
        'an enum bare and in a list' => ['list<\Workbench\App\Enums\OrderStatus>|\Workbench\App\Enums\OrderStatus'],
        'a space between a shape key and its marker' => ['array{a ?: int, b: string}'],
        'a space between a shape key and its colon' => ['array{a : int}'],
        'a space before a shape\'s brace' => ['array {a: int}'],
        'a space before a nested shape\'s brace' => ['array{a: array {b: int}}'],
        'a list slot holding a scalar or a list' => ['list<int|list<string>>'],
        'a list slot holding two lists' => ['list<list<int>|list<string>>'],
        'a record slot holding a scalar or a list' => ['array<string, int|list<int>>'],
        'a list slot holding two records' => ['list<array<string, int>|array<string, bool>>'],
        'a list slot holding a shape or null' => ['list<array{a: int}|null>'],
        'a shape member whose class the shape reader leaves unknown' => ['array{a: User}'],
        'a bare collection, which resolves to unknown elements' => ['\Illuminate\Support\Collection'],
    ]);

    test('binds a tag built only from supported forms, and reads it in full', function (string $type, ?string $read) {
        $reader = resolve(PropertyDocblockTypeReader::class);
        $context = new ReflectionClass(DeclaredPrecedenceResource::class);

        expect($reader->extractVarTag('/** @var '.$type.' $d */', $context))->toBe([$type, 'd'])
            ->and($reader->readDeclared($type, $context)['type'] ?? null)->toBe($read);
    })->with([
        'each scalar' => ['int|string|bool|float|null|true|false', 'number | string | boolean | null | true | false'],
        'a nullable scalar' => ['?int', 'number | null'],
        'an imported class' => ['CartTotals', '{ subtotal: number; chargeable: boolean; count: number; hasExtras: boolean }'],
        'an imported interface, which only names a receiver' => ['MustVerifyEmail', null],
        'a nullable model' => ['?User', 'User | null'],
        'a union of models' => ['Comment|User|null', 'Comment | User | null'],
        'a qualified enum' => ['\Workbench\App\Enums\Status', 'StatusType'],
        'a list' => ['list<User|null>', '(User | null)[]'],
        'a list of nullables' => ['list<?int>', '(number | null)[]'],
        'an int-keyed array' => ['array<int, User>', 'User[]'],
        'a string-keyed array of lists' => ['array<string, list<int>>', 'Record<string, number[]>'],
        'a shape' => [
            'array{a: int, b?: string, c: ?int, d: array{e: list<string>}}',
            '{ a: number; b?: string; c: number | null; d: { e: string[] } }',
        ],
        'a shape with a trailing comma' => ['array{a: int,}', '{ a: number }'],
        'a shape spaced inside its braces' => ['array{ a: int, b?: string }', '{ a: number; b?: string }'],
        'a nullable shape member written as a union' => ['array{a: array{b: int}|null}', '{ a: { b: number } | null }'],
        'a null-first shape member' => ['array{a: null|array{b: int}}', '{ a: null | { b: number } }'],
        'a nullable shape' => ['array{a: int}|null', '{ a: number } | null'],
        'a list of shapes' => ['list<array{a: int}>', '{ a: number }[]'],
        'lists in a union' => ['list<int>|list<string>', 'number[] | string[]'],
        'a support collection' => ['\Illuminate\Support\Collection<int, Comment>', 'Comment[]'],
        'an Eloquent collection or null' => ['\Illuminate\Database\Eloquent\Collection<string, User>|null', 'Record<string, User> | null'],
    ]);

    test('keeps the loaded relation\'s model for a member read when the declaration admits it', function () {
        $closure = fn (string $declared): Expr => new AstParser()->parseSource('<?php $this->whenLoaded("author", function () {
            /** @var '.$declared.' $o */
            $o = json_decode("");

            return $o->name;
        });')[0]->expr;
        $resolved = fn (string $declared): string => new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class)
            ->resolve($closure($declared))['type'];

        expect($resolved('\Illuminate\Database\Eloquent\Model'))->toBe('string')
            ->and($resolved('\Workbench\App\ValueObjects\CartTotals'))->toBe('unknown');
    });
    test('types a local from its assigned value when the engine reads it, and from the tag only where that is vague', function () {
        $value = function (string $body, ?string $modelOf = null): string {
            [$scope, $returned] = inlineVarReturned($body);

            if ($modelOf !== null) {
                $scope->varModelBindings['t'] = $modelOf;
            }

            return new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class, 'toArray', null, $scope)
                ->resolve($returned)['type'];
        };
        $receiver = function (string $body): ?array {
            [$scope, $returned] = inlineVarReturned($body);

            return resolve(ReceiverClassResolver::class)->resolve($returned, $scope)?->classes;
        };

        expect($value('/** @var int|null $n */ $n = $this->resource->title; return $n;'))->toBe('string')
            ->and($value('/** @var \Workbench\App\Models\Comment $c */ $c = $this->resource->author; return $c;'))->toBe('User')
            ->and($value('/** @var string $g */ $g = ["a" => 1]; return $g;'))->toBe('{ a: number }')
            ->and($value('/** @var array{a: int} $i */ $i = $this->resource->title; return $i;'))->toBe('string')
            ->and($value('/** @var \Workbench\App\ValueObjects\CartTotals $j */ $j = $this->resource->title; return $j;'))->toBe('string')
            ->and($value('/** @var array{id: int, name: string} $w */ $w = $this->resource->author->only(["id", "name"]); return $w;'))
            ->toBe("Pick<User, 'id' | 'name'>")
            ->and($value('/** @var \Workbench\App\Models\SubscribedTeam $t */ $t = json_decode(""); return $t;', Team::class))
            ->toBe('SubscribedTeam')
            ->and($value('/** @var string|null $a */ $a = $this->resource->id > 0 ? json_decode("\"x\"") : null; return $a;'))
            ->toBe('string | null')
            ->and($value('/** @var string $b */ $b = $this->resource->id > 0 ? json_decode("\"x\"") : null; return $b;'))->toBe('string')
            ->and($value('/** @var int|string $c */ $c = $this->resource->id > 0 ? json_decode("\"x\"") : 0; return $c;'))
            ->toBe('number')
            ->and($value('/** @var string|null $d */ $d = json_decode("\"x\"") ?: null; return $d;'))->toBe('string | null')
            ->and($value('/** @var string|null $k */ $k = $this->resource->id > 0 ? $this->resource->title : json_decode("\"x\""); return $k;'))
            ->toBe('string')
            ->and($value('/** @var string|int $k */ $k = $this->resource->title ?: json_decode("1"); return $k;'))->toBe('string')
            ->and($value('/** @var array<string, int|string> $k */ $k = ["a" => $this->resource->id, "b" => $this->resource->id > 0 ? $this->resource->title : json_decode("\"x\"")]; return $k;'))
            ->toBe('{ a: number; b: string }')
            ->and($value('/** @var \Workbench\App\Enums\Status|null $k */ $k = $this->resource->status ?? \Workbench\App\Enums\Status::Draft; return $k;'))
            ->toBe('StatusType')
            ->and($value('/** @var string $n */ $n = null; return $n;'))->toBe('null')
            ->and($receiver('/** @var \Workbench\App\Models\Comment $c */ $c = $this->author; return $c;'))->toBe([User::class])
            ->and($receiver('/** @var \Illuminate\Contracts\Auth\MustVerifyEmail $k */ $k = $this->author; return $k;'))->toBe([User::class])
            ->and($receiver('/** @var \Workbench\App\Models\SubscribedTeam $t */ $t = new \Workbench\App\Models\Team; return $t;'))
            ->toBe([Team::class])
            ->and($receiver('/** @var \Workbench\App\Models\SubscribedTeam $m */ $m = $this->resource->getRelationValue("x"); return $m;'))
            ->toBe([SubscribedTeam::class])
            ->and($receiver('/** @var \Workbench\App\Models\User $u */ $u = new \Illuminate\Database\Eloquent\Model; return $u;'))
            ->toBe([User::class]);
    });

    test('keeps a conditional value the tag types optional, since Laravel drops the key when the condition fails', function () {
        $resolved = function (string $body): array {
            [$scope, $returned] = inlineVarReturned($body);
            $result = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class, 'toArray', null, $scope)
                ->resolve($returned);

            return [$result['type'], $result['optional']];
        };

        expect($resolved('/** @var \Workbench\App\Models\User $a */ $a = $this->whenLoaded("undeclaredRelation"); return $a;'))
            ->toBe(['User', true])
            ->and($resolved('/** @var array{a: int} $b */ $b = $this->when($request->boolean("x"), fn () => json_decode("{}", true)); return $b;'))
            ->toBe(['{ a: number }', true])
            ->and($resolved('/** @var int $c */ $c = $this->whenHas("undeclared_attr"); return $c;'))->toBe(['number', true])
            ->and($resolved('/** @var int $n */ $n = json_decode("1"); return $n;'))->toBe(['number', false]);
    });

    test('lets a known reading of the assigned value stand over the loaded relation\'s model inside a whenLoaded closure', function () {
        [$scope, $returned] = inlineVarReturned('/** @var \Illuminate\Database\Eloquent\Model $m */ $m = $this->resource->author;
            return $this->whenLoaded("comments", fn () => $m->name);');

        expect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class, 'toArray', null, $scope)
            ->resolve($returned)['type'])->toBe('string');
    });

    test('types a closure parameter reassigned under a tag from its new value, not the value it was passed', function () {
        $resolved = fn (string $php): string => new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class)
            ->resolve(new AstParser()->parseSource('<?php '.$php.';')[0]->expr)['type'];

        expect($resolved('$this->whenHas("title", function ($t) { /** @var int $t */ $t = strlen($t); return $t; })'))->toBe('number')
            ->and($resolved('$this->transform($this->resource->title, function ($t) { /** @var int $t */ $t = strlen($t); return $t; })'))
            ->toBe('number')
            ->and($resolved('$this->whenLoaded("author", function (\Workbench\App\Models\User $a) { /** @var string $a */ $a = $a->name; return $a; })'))
            ->toBe('string')
            ->and($resolved('$this->whenLoaded("author", function (\Workbench\App\Models\User $a) { /** @var \Workbench\App\Models\Comment|null $a */ $a = $a->comments->first(); return $a; })'))
            ->toBe('Comment | null');
    });

    test('pins the precedence through a workbench resource', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => DeclaredPrecedenceResource::class])->content)
            ->toContain("import type { StatusType } from '../../enums';")
            ->toContain("import type { Comment } from '../../models';")
            ->toContain(<<<'TS'
                export interface DeclaredPrecedenceResource
                {
                    picked: string | null;
                    picked_strict: string;
                    picked_or_zero: number;
                    elvis: string | null;
                    literal: { a: number };
                    heading: string;
                    title_as_totals: string;
                    verifiable_email: string;
                    callable: unknown;
                    callable_shape: unknown;
                    closure: unknown;
                    intersection: unknown;
                    tuple: unknown;
                    decoded: unknown;
                    literal_keys: unknown;
                    quoted_key: unknown;
                    kept_title: string;
                    kept_record: { a: number; b: string };
                    kept_shape: { a: number; b: string };
                    kept_status: StatusType;
                    length?: number;
                    transformed_length?: number;
                    author_name?: string;
                    first_comment?: Comment | null;
                }
                TS);
    });

    test('pins an optional declared local and the whenLoaded() rule through a workbench resource', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => DeclaredConditionalResource::class])->content)
            ->toContain("import type { User } from '../../models';")
            ->toContain(<<<'TS'
                export interface DeclaredConditionalResource
                {
                    reviewer?: User;
                    flags?: { a: number };
                    views?: number;
                    author_name?: string;
                }
                TS);
    });
});
