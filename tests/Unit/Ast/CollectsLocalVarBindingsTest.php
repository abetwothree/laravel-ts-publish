<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UnseenWritesResource;
use PhpParser\Node;

/**
 * The names collectVariableWrites() reports for a parsed body, in order, with UnseenWritesResource as the subject.
 *
 * @return list<string>
 */
function localWriteNames(string $body): array
{
    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * The names the write scan reports.
         *
         * @param  array<Node>  $stmts
         * @return list<string>
         */
        public function names(array $stmts): array
        {
            return array_column($this->collectVariableWrites($stmts, new ReflectionClass(UnseenWritesResource::class)), 0);
        }
    };

    return $host->names(new AstParser()->parseSource('<?php '.$body));
}

// UnseenWritesResource declares fillByReference() and fillInstance(), each taking its argument by reference.
it('reports each write kind', function (string $body, array $names) {
    expect(localWriteNames($body))->toBe($names);
})->with([
    'catch' => ['try {} catch (Exception $e) {}', ['e']],
    'non-capturing catch' => ['try {} catch (Exception) {}', []],
    'unset' => ['unset($a, $b[1]);', ['a', 'b']],
    'static' => ['static $s = 1, $t;', ['s', 't']],
    'global' => ['global $g;', ['g']],
    'by-ref function argument' => ['preg_match("/x/", $s, $m);', ['m']],
    'by-ref key argument' => ['sort($a["k"]);', ['a']],
    'by-value function argument' => ['strlen($s);', []],
    'pointer function' => ['end($a); reset($a);', []],
    'by-ref static method' => ['self::fillByReference($x);', ['x']],
    'by-ref instance method' => ['$this->fillInstance($x);', ['x']],
    'unnamed receiver' => ['$other->fill($x);', []],
    'named by-ref argument' => ['preg_match(subject: $s, pattern: "/x/", matches: $m);', ['m']],
    'foreach by reference' => ['foreach ($list as &$v) {}', ['v', 'list']],
    'closure parameter write' => ['$f = function ($x) { $x = 1; };', ['f']],
    'closure local write' => ['$f = function () { $y = 1; };', ['f']],
    'closure by-value use write' => ['$f = function () use ($y) { $y = 1; };', ['f', 'y']],
    'closure by-ref use' => ['$f = function () use (&$y) {};', ['f', 'y']],
    'closure $this write' => ['$f = function () { $this->x = 1; };', ['f', 'this']],
    'arrow parameter write' => ['$f = fn ($x) => $x = 1;', ['f']],
    'arrow capture write' => ['$f = fn () => $y = 1;', ['f', 'y']],
    'nested class' => ['$o = new class { public function m() { $z = 1; } };', ['o']],
]);
