<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ChildOfParentOwnedMethod;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\TwoClassesFirst;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\TwoClassesSecond;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UsesLabelledTrait;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

// PostResource declares toArray() itself, takes includeMorphValue() from a trait, and inherits resolve()
// from JsonResource — three different declaring files, which is exactly what locateOwn discriminates on.
$declaredElsewhere = function (string $class, string $method): bool {
    $reflection = new ReflectionClass($class);

    return $reflection->getMethod($method)->getFileName() !== $reflection->getFileName();
};

// Reads the single `return '<literal>';` statement a fixture's label() method returns.
$literal = fn (?MethodContext $ctx): ?string => $ctx?->method->stmts[0]->expr->value ?? null;

it('locates a method declared in the class own file', function () {
    $locator = new MethodLocator(new AstParser);

    $context = $locator->locateOwn(UserResource::class, 'toArray');

    expect($context)->not->toBeNull()
        ->and($context->method->name->toString())->toBe('toArray')
        ->and($context->reflection->getName())->toBe(UserResource::class);
});

it('locateOwn misses an inherited method, locate finds it in the declaring file', function () {
    $locator = new MethodLocator(new AstParser);

    // User inherits save() from Eloquent's Model; it is not declared in User's own file.
    expect($locator->locateOwn(User::class, 'save'))->toBeNull()
        ->and($locator->locate(User::class, 'save'))->not->toBeNull();
});

it('locate matches method names case-insensitively, like PHP dispatch', function () {
    $locator = new MethodLocator(new AstParser);

    expect($locator->locate(UserResource::class, 'TOARRAY'))->not->toBeNull();
});

it('locateOwn matches an own method under any casing, like PHP dispatch', function (string $spelling) {
    // Route action strings ("Controller@Index") and AstEngine::analyzeMethod() pass the caller's casing
    // straight through, and PHP dispatches all of these to the same declaration.
    $locator = new MethodLocator(new AstParser);

    $context = $locator->locateOwn(PostResource::class, $spelling);

    expect($context)->not->toBeNull()
        ->and($context->method->name->toString())->toBe('toArray')
        ->and($context->reflection->getName())->toBe(PostResource::class);
})->with(['toArray', 'toarray', 'TOARRAY', 'ToArray']);

it('locateOwn still misses a trait method under any casing', function (string $spelling) use ($declaredElsewhere) {
    // Guards the delegation contract: a HIT here would make every caller treat a trait method as own code.
    expect($declaredElsewhere(PostResource::class, 'includeMorphValue'))->toBeTrue();

    $locator = new MethodLocator(new AstParser);

    expect($locator->locateOwn(PostResource::class, $spelling))->toBeNull();
})->with(['includeMorphValue', 'INCLUDEMORPHVALUE', 'includemorphvalue']);

it('locateOwn still misses an inherited method under any casing', function (string $spelling) use ($declaredElsewhere) {
    expect($declaredElsewhere(PostResource::class, 'resolve'))->toBeTrue();

    $locator = new MethodLocator(new AstParser);

    expect($locator->locateOwn(PostResource::class, $spelling))->toBeNull();
})->with(['resolve', 'RESOLVE', 'Resolve']);

it('memoizes locateOwn per declared method, not per spelling', function (string $first, string $second) {
    // One shared entry is only correct because every spelling resolves to the same declaration; assert the
    // second lookup returns the identical object rather than whatever the first caller's casing produced.
    $locator = new MethodLocator(new AstParser);

    $a = $locator->locateOwn(PostResource::class, $first);
    $b = $locator->locateOwn(PostResource::class, $second);

    expect($a)->not->toBeNull()
        ->and($b)->toBe($a);
})->with([
    ['TOARRAY', 'toArray'],
    ['toArray', 'TOARRAY'],
    ['ToArRaY', 'toarray'],
]);

it('returns null for a missing class or method', function () {
    $locator = new MethodLocator(new AstParser);

    expect($locator->locateOwn('Not\A\Class', 'x'))->toBeNull()
        ->and($locator->locateOwn(User::class, 'notAMethod'))->toBeNull();
});

it('memoizes a miss so a repeated lookup never re-parses the file', function () {
    // A spy AstParser counts parseFile() calls so we can prove the second lookup skips parsing entirely,
    // not merely that it returns an equal result.
    $parser = new class extends AstParser
    {
        public int $calls = 0;

        public function parseFile(string $path): array
        {
            $this->calls++;

            return parent::parseFile($path);
        }
    };
    $locator = new MethodLocator($parser);

    expect($locator->locateOwn(User::class, 'save'))->toBeNull();

    $callsAfterFirstMiss = $parser->calls;

    expect($callsAfterFirstMiss)->toBeGreaterThan(0)
        ->and($locator->locateOwn(User::class, 'save'))->toBeNull()
        ->and($parser->calls)->toBe($callsAfterFirstMiss);
});

it('returns each class its own body when two classes share a file and a method name', function () use ($literal) {
    require_once __DIR__.'/Fixtures/TwoClassesOneFile.php';
    $locator = new MethodLocator(new AstParser);

    expect($literal($locator->locateOwn(TwoClassesFirst::class, 'label')))->toBe('first')
        ->and($literal($locator->locateOwn(TwoClassesSecond::class, 'label')))->toBe('second')
        ->and($literal($locator->locate(TwoClassesSecond::class, 'label')))->toBe('second');
});

it('locate still resolves a method inherited from a parent in another file', function () use ($literal) {
    // Only ParentOwnedMethod declares label(); the two-file split is what locate()'s $file switch
    // exercises, and one matching owner never reaches the two-classes disambiguation path at all.
    $locator = new MethodLocator(new AstParser);

    expect($literal($locator->locate(ChildOfParentOwnedMethod::class, 'label')))->toBe('from parent');
});

it('locate still resolves a method imported from a trait', function () use ($literal) {
    // The trait file's only ClassLike is the trait itself; getDeclaringClass() on a trait-imported
    // method reports the USING class, which never appears in that file — findIn() must not require it to.
    $locator = new MethodLocator(new AstParser);

    expect($literal($locator->locate(UsesLabelledTrait::class, 'label')))->toBe('from trait');
});
