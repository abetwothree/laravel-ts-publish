<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\CallChainWalker;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\PostTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use InertiaUI\Table\Table;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Models\Post;

/**
 * Parse a single-statement snippet and return its top-level expression — the full chain head
 * callers pass to CallChainWalker, exactly as the three retrofitted analyzer sites do.
 */
function firstStatementExpr(string $php): Expr
{
    $stmts = new AstParser()->parseSource($php);
    $first = $stmts[0];

    expect($first)->toBeInstanceOf(Expression::class);

    return $first->expr;
}

it('unwraps a nested MethodCall chain down to its StaticCall root', function () {
    $expr = firstStatementExpr('<?php Workbench\App\Models\Post::query()->where("id", 1)->paginate();');

    $root = new CallChainWalker()->chainRoot($expr);

    expect($root)->toBeInstanceOf(StaticCall::class)
        ->and($root->class->toString())->toBe(Post::class);
});

it('returns a non-MethodCall expression unchanged', function () {
    $expr = firstStatementExpr('<?php Workbench\App\Models\Post::class;');

    expect(new CallChainWalker()->chainRoot($expr))->toBe($expr);
});

it('resolves a paginator chain rooted at a static call to the model class', function () {
    $expr = firstStatementExpr('<?php Workbench\App\Models\Post::query()->where("id", 1)->paginate();');

    $result = new CallChainWalker()->resolveRootClass($expr, Model::class);

    expect($result)->toBe(Post::class);
});

it('returns null for a chain with no class-rooted terminal', function () {
    $expr = firstStatementExpr('<?php $service->query()->paginate();');

    expect(new CallChainWalker()->resolveRootClass($expr, Model::class))->toBeNull();
});

it('returns null when the resolved root class does not extend the base class', function () {
    $expr = firstStatementExpr('<?php Workbench\App\Models\Post::query();');

    expect(new CallChainWalker()->resolveRootClass($expr, JsonResource::class))->toBeNull();
});

it('returns null gracefully when $baseClass does not exist, instead of erroring', function () {
    // Decision: the InertiaTableAnalyzer table base is a string FQCN the host app may not have
    // installed. is_a($fqcn, $baseClass, true) must stay silent, not require class_exists($baseClass).
    $expr = firstStatementExpr('<?php Workbench\App\Models\Post::query();');

    expect(new CallChainWalker()->resolveRootClass($expr, 'Some\Totally\Nonexistent\BaseClass'))->toBeNull();
});

it('resolves a New_-rooted chain only when allowNew is true', function () {
    $expr = firstStatementExpr(
        '<?php new \AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\PostTable()->defaultSort("-id");'
    );

    $walker = new CallChainWalker;

    expect($walker->resolveRootClass($expr, Table::class, allowNew: false))->toBeNull()
        ->and($walker->resolveRootClass($expr, Table::class, allowNew: true))->toBe(PostTable::class);
});

it('resolves a ClassConstFetch-rooted expression only when allowClassConst is true', function () {
    $expr = firstStatementExpr('<?php Workbench\App\Models\Post::class;');

    $walker = new CallChainWalker;

    expect($walker->resolveRootClass($expr, Model::class, allowClassConst: false))->toBeNull()
        ->and($walker->resolveRootClass($expr, Model::class, allowClassConst: true))->toBe(Post::class);
});

it('records the resolved class as a cache dependency only when recordDependency is true', function () {
    $expr = firstStatementExpr('<?php Workbench\App\Models\Post::query()->paginate();');
    $walker = new CallChainWalker;
    $postFile = (new ReflectionClass(Post::class))->getFileName();

    DependencyRecorder::start();
    $walker->resolveRootClass($expr, Model::class, recordDependency: false);
    $pathsWhenOff = DependencyRecorder::paths();
    DependencyRecorder::stop();
    DependencyRecorder::reset();

    DependencyRecorder::start();
    $walker->resolveRootClass($expr, Model::class, recordDependency: true);
    $pathsWhenOn = DependencyRecorder::paths();
    DependencyRecorder::stop();
    DependencyRecorder::reset();

    expect($pathsWhenOff)->toBe([])
        ->and($pathsWhenOn)->toContain($postFile);
});
