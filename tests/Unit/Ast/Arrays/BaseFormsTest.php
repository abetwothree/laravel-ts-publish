<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\BaseForm;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\BaseForms;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Http\Resources\PostResource;

it('classifies a literal and a + itself, and any other form as the host reads it', function (string $source, ?BaseForm $form) {
    $statement = resolve(AstParser::class)->parseSource('<?php '.$source.';')[0];
    $expr = $statement instanceof Expression ? $statement->expr : throw new LogicException('An expression statement.');
    $host = static fn (Expr $base): ?BaseForm => match (true) {
        $base instanceof Variable => BaseForm::Local,
        $base instanceof FuncCall => BaseForm::Merge,
        $base instanceof MethodCall => BaseForm::Host,
        default => null,
    };

    expect(BaseForms::classify($expr, new AnalysisScope(new ReflectionClass(PostResource::class)), $host))
        ->toBe($form);
})->with([
    'a literal' => ['["a" => 1]', BaseForm::Literal],
    'a local' => ['$d', BaseForm::Local],
    'a +' => ['["a" => 1] + $d', BaseForm::Plus],
    'a merge' => ['array_merge(["a" => 1], ["b" => 2])', BaseForm::Merge],
    'a host base' => ['$this->basics()', BaseForm::Host],
    'a local no name spells' => ['$$name', null],
    'a form no one reads' => ['"text"', null],
]);

it('reads a local whose read is under way as no base', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $form = $scope->readingLocal(
        'd',
        fn (): ?BaseForm => BaseForms::classify(new Variable('d'), $scope, static fn (): BaseForm => BaseForm::Local),
    );

    expect($form)->toBeNull();
});
