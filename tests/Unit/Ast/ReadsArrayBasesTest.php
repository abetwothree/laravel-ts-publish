<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ReturnedHelperVariableResource;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp\Plus;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Models\Tag;

it('reads a base exactly where the gate admits one', function (string $body, bool $reads) {
    $analyzer = new class(new ReflectionClass(ReturnedHelperVariableResource::class), Tag::class) extends ResourceAstAnalyzer
    {
        /**
         * The gate's answer for the value a body's last write of `$d` assigns, beside whether the reader reads it, with
         * the body's locals bound as a method body binds them.
         *
         * @param  array<Stmt>  $stmts
         * @return array{bool, bool}
         */
        public function gateAndReader(array $stmts): array
        {
            $last = end($stmts);
            $value = $last instanceof Expression && ($last->expr instanceof Assign || $last->expr instanceof Plus)
                ? $last->expr->expr
                : throw new LogicException('Each body ends with a whole write of $d.');
            $this->seedVarBindings(null, $stmts);

            return [$this->readsAsArray($value), $this->analyzeArrayExpression($value) !== null];
        }
    };

    expect($analyzer->gateAndReader(resolve(AstParser::class)->parseSource('<?php '.$body)))->toBe([$reads, $reads]);
})->with([
    'a literal' => ['$d = ["a" => 1];', true],
    'a + of literals' => ['$d = ["a" => 1] + ["b" => 2];', true],
    'a += of a literal' => ['$d += ["b" => 2];', true],
    'the own model only() with literal keys' => ['$d = $this->only(["id"]);', true],
    'the subject\'s own helper' => ['$d = $this->basics();', true],
    'an array_merge() of literals' => ['$d = array_merge(["a" => 1], ["b" => 2]);', true],
    'a local written once with a literal' => ['$base = ["a" => 1]; $d = $base;', true],
    'a + of a local and a literal' => ['$base = ["a" => 1]; $d = $base + ["b" => 2];', true],
    'a parent:: call' => ['$d = parent::toArray($request);', true],
    'an opaque base' => ['$d = $this->resource->toArray();', false],
    'only() with runtime keys' => ['$d = $this->only($keys);', false],
    'a helper the subject lacks' => ['$d = $this->noSuchHelper();', false],
    'a + with an opaque side' => ['$d = ["a" => 1] + $this->resource->toArray();', false],
    'an array_merge() with an opaque argument' => ['$d = array_merge(["a" => 1], $this->resource->toArray());', false],
    'a function call' => ['$d = compact("a");', false],
    'a local written once with an opaque base' => ['$base = $this->resource->toArray(); $d = $base;', false],
    'a local whose write names itself' => ['$base = $base + ["a" => 1]; $d = $base;', false],
    'a local read after the last of its two writes' => ['$base = ["a" => 1]; $base = ["b" => 2]; $d = $base;', true],
]);

it('marks a local as being read only while its read runs, even one that throws', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReturnedHelperVariableResource::class));

    expect($scope->readingLocal('base', fn (): array => $scope->readingLocals))->toBe(['base' => true])
        ->and($scope->readingLocals)->toBe([])
        ->and(fn () => $scope->readingLocal('base', fn () => throw new RuntimeException('read failed')))
        ->toThrow(RuntimeException::class, 'read failed')
        ->and($scope->readingLocals)->toBe([]);
});
