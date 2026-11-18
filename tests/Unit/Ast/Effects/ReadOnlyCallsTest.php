<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Effects\ReadOnlyCalls;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\UnpinningResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\OwnWhenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergedResourceCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostCardResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\PrettyPrinter\Standard;
use Workbench\App\Http\Resources\UserResource;

it('names a helper the framework lists, run on $this', function (
    string $call,
    ?string $helper,
    string $subject = PostCardResource::class,
) {
    $expr = runtimeClassCall($call);
    $scope = new AnalysisScope(new ReflectionClass($subject));
    assert($expr instanceof MethodCall || $expr instanceof StaticCall);

    expect(ReadOnlyCalls::resourceHelper($expr, $scope)?->getName())->toBe($helper);
})->with([
    'when()' => ['$this->when($c, 1)', 'when'],
    'whenLoaded()' => ["\$this->whenLoaded('author')", 'whenLoaded'],
    'mergeWhen()' => ['$this->mergeWhen($c, [])', 'mergeWhen'],
    'parent::when()' => ['parent::when($c, 1)', 'when'],
    'whenLoaded() on a collection' => ["\$this->whenLoaded('author')", 'whenLoaded', MergedResourceCollection::class],
    'a method the subject declares' => ['$this->header()', null],
    'a self:: method the subject declares' => ['self::header()', null],
    'a helper the subject overrides' => ['$this->when($c, 1)', null, OwnWhenResource::class],
    'parent::when() past the override' => ['parent::when($c, 1)', 'when', OwnWhenResource::class],
    'a method no class declares' => ['$this->missing()', null],
    'a helper\'s name on another receiver' => ['$this->resource->when($c, 1)', null],
    'a JsonResource method the framework does not list' => ['$this->resolve()', null],
    'a static JsonResource method the framework does not list' => ['self::collection($items)', null],
    'preserveQuery() on a collection' => ['$this->preserveQuery()', null, MergedResourceCollection::class],
]);

it('names the helper a method of a class runs, where the framework declares it', function (
    string $method,
    ?string $helper,
    string $class = PostCardResource::class,
) {
    expect(ReadOnlyCalls::helperOn(new ReflectionClass($class), $method)?->getName())->toBe($helper);
})->with([
    'when()' => ['when', 'when'],
    'whenLoaded(), in another case' => ['WHENLOADED', 'whenLoaded'],
    'a method the framework does not list' => ['additional', null],
    'a method the class lacks' => ['missing', null],
    'a helper the class overrides' => ['when', null, OwnWhenResource::class],
]);

it('gives the arguments a helper calls, as its value and default', function (string $call, array $callbacks) {
    $expr = runtimeClassCall($call);
    assert($expr instanceof MethodCall);

    expect(array_map(
        new Standard()->prettyPrintExpr(...),
        ReadOnlyCalls::callbacks(CallArguments::for($expr, new ReflectionMethod(JsonResource::class, 'when'))),
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

it('reads a callable relative to the subject, which runs on $this', function (string $value, ?array $target) {
    expect(ReadOnlyCalls::relativeCallable(runtimeClassCall($value)))->toBe($target)
        ->and(ReadOnlyCalls::namesRelativeCallable(runtimeClassCall($value)))->toBe($target !== null);
})->with([
    'static::m' => ["'static::writer'", ['static', 'writer']],
    'SELF::m, in another case' => ["'SELF::reader'", ['self', 'reader']],
    '[parent, m]' => ["['parent', 'baseWriter']", ['parent', 'baseWriter']],
    '[parent, a computed name]' => ["['parent', \$name]", ['parent', null]],
    'a relative class and no method' => ["'self::'", ['self', null]],
    'a named class' => ["'Foo::bar'", null],
    '[$this, m]' => ["[\$this, 'writer']", null],
]);

it('tells a `::class` name from another class constant', function (string $value, bool $named) {
    expect(ReadOnlyCalls::isClassName(runtimeClassCall($value)))->toBe($named);
})->with([
    'Foo::class' => ['Foo::class', true],
    'Foo::CLASS' => ['Foo::CLASS', true],
    'Foo::BAR' => ['Foo::BAR', false],
    'a string' => ["'Foo'", false],
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

it('knows Laravel\'s auth() helper', function (string $call, bool $helper) {
    $expr = runtimeClassCall($call);
    assert($expr instanceof FuncCall);

    expect(ReadOnlyCalls::authHelper($expr))->toBe($helper);
})->with([
    'auth()' => ['auth()', true],
    'another Laravel helper' => ['now()', false],
]);

it('knows a resource built with JsonResource\'s own constructor', function (string $class, bool $builds) {
    expect(ReadOnlyCalls::buildsResource($class))->toBe($builds);
})->with([
    'a resource' => [UserResource::class, true],
    'JsonResource itself' => [JsonResource::class, true],
    'a collection, with its own constructor' => [AnonymousResourceCollection::class, false],
    'another class' => [Model::class, false],
    'a class no file declares' => ['Missing\\Resource', false],
]);

it('knows a resource\'s make() and collection(), where they build it as `new` does', function (string $call, bool $builds) {
    $expr = runtimeClassCall($call);
    assert($expr instanceof StaticCall);

    expect(ReadOnlyCalls::buildsResourceStatically($expr))->toBe($builds);
})->with([
    'make()' => ['\\'.UserResource::class.'::make($post)', true],
    'collection(), in another case' => ['\\'.UserResource::class.'::COLLECTION($posts)', true],
    'make() of a resource with its own constructor' => ['\\'.UnpinningResource::class.'::make($post)', false],
    'another static method' => ['\\'.UserResource::class.'::wrap(\'data\')', false],
    'self::make()' => ['self::make($post)', false],
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
    'hasAppended()' => ['hasAppended', true],
    'save()' => ['save', false],
]);

it('lists only methods Eloquent declares, trashed() on the SoftDeletes trait', function () {
    $model = new ReflectionClass(Model::class);
    $offModel = array_filter(ReadOnlyCalls::MODEL_READERS, fn (string $method): bool => ! $model->hasMethod($method));

    expect(array_values($offModel))->toBe(['trashed'])
        ->and(new ReflectionClass(SoftDeletes::class)->hasMethod('trashed'))->toBeTrue();
});
