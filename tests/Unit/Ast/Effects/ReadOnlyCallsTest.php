<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Effects\ReadOnlyCalls;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostCardResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\PrettyPrinter\Standard;

it('names the framework helper a call on $this runs', function (string $call, ?string $helper) {
    $expr = runtimeClassCall($call);
    $scope = new AnalysisScope(new ReflectionClass(PostCardResource::class));
    assert($expr instanceof MethodCall || $expr instanceof StaticCall);

    expect(ReadOnlyCalls::resourceHelper($expr, $scope)?->getName())->toBe($helper);
})->with([
    'when()' => ['$this->when($c, 1)', 'when'],
    'whenLoaded()' => ["\$this->whenLoaded('author')", 'whenLoaded'],
    'mergeWhen()' => ['$this->mergeWhen($c, [])', 'mergeWhen'],
    'parent::when()' => ['parent::when($c, 1)', 'when'],
    'a method the subject declares' => ['$this->header()', null],
    'a self:: method the subject declares' => ['self::header()', null],
    'a method no class declares' => ['$this->missing()', null],
    'a helper\'s name on another receiver' => ['$this->resource->when($c, 1)', null],
]);

it('gives the arguments a helper calls, as its value and default', function (string $call, array $callbacks) {
    $expr = runtimeClassCall($call);
    assert($expr instanceof MethodCall);

    expect(array_map(
        new Standard()->prettyPrintExpr(...),
        ReadOnlyCalls::callbacks($expr, new ReflectionMethod(JsonResource::class, 'when')),
    ))->toBe($callbacks);
})->with([
    'positional' => ['$this->when($c, $v, $d)', ['$v', '$d']],
    'named, in any order' => ['$this->when(default: $d, condition: $c, value: $v)', ['$v', '$d']],
    'no default' => ['$this->when($c, $v)', ['$v']],
]);

it('tells a callable a value names from a closure written there', function (string $value, bool $names) {
    expect(ReadOnlyCalls::namesCallable(runtimeClassCall($value)))->toBe($names);
})->with([
    'a first-class callable' => ['$this->m(...)', true],
    'a static method\'s name' => ["'Foo::bar'", true],
    'a class-and-method array' => ["[Foo::class, 'bar']", true],
    'a closure' => ['function () {}', false],
    'an arrow function' => ['fn () => 1', false],
]);

it('knows Laravel\'s URL helpers', function (string $call, bool $helper) {
    $expr = runtimeClassCall($call);
    assert($expr instanceof FuncCall);

    expect(ReadOnlyCalls::urlHelper($expr))->toBe($helper);
})->with([
    'route()' => ["route('posts.show', \$this->resource)", true],
    'url()' => ["url('/posts')", true],
    'another Laravel helper' => ['now()', false],
]);

it('knows the Gate facade\'s checks', function (string $call, bool $check) {
    $expr = runtimeClassCall($call);
    assert($expr instanceof StaticCall);

    expect(ReadOnlyCalls::gateCheck($expr))->toBe($check);
})->with([
    'Gate::allows()' => ["Gate::allows('view', \$this->resource)", true],
    'the facade by its full name' => ["\\Illuminate\\Support\\Facades\\Gate::denies('view', \$this->resource)", true],
    'a Gate method that defines' => ["Gate::define('view', \$callback)", false],
    'another class' => ["Policy::allows('view', \$this->resource)", false],
]);

it('knows the model methods that only read, whatever their case', function (string $method, bool $reads) {
    expect(ReadOnlyCalls::readsModel($method))->toBe($reads);
})->with([
    'getKey()' => ['getKey', true],
    'getkey()' => ['getkey', true],
    'save()' => ['save', false],
]);

it('lists only methods Eloquent declares, trashed() on the SoftDeletes trait', function () {
    $model = new ReflectionClass(Model::class);
    $offModel = array_filter(ReadOnlyCalls::MODEL_READERS, fn (string $method): bool => ! $model->hasMethod($method));

    expect(array_values($offModel))->toBe(['trashed'])
        ->and(new ReflectionClass(SoftDeletes::class)->hasMethod('trashed'))->toBeTrue();
});
