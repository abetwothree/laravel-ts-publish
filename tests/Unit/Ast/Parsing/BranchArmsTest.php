<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\BranchArms;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use PhpParser\PrettyPrinter\Standard;

it('reads the values a ternary or a match may give', function (string $source, ?array $printed) {
    $statement = resolve(AstParser::class)->parseSource('<?php '.$source.';')[0];
    $expr = $statement instanceof Expression ? $statement->expr : throw new LogicException('An expression statement.');
    $arms = BranchArms::of($expr);

    expect($arms === null ? null : array_map(static fn (Expr $arm): string => new Standard()->prettyPrintExpr($arm), $arms))
        ->toBe($printed);
})->with([
    'a ternary' => ['$c ? ["a" => 1] : $d', ['["a" => 1]', '$d']],
    'a ?: whose condition is its truthy arm' => ['$d ?: ["a" => 1]', ['$d', '["a" => 1]']],
    'a match with a default' => ['match ($k) { 1 => $a, default => $b }', ['$a', '$b']],
    'a plain call' => ['f()', null],
]);
