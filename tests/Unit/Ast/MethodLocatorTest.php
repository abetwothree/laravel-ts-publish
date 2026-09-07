<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ChildOfParentOwnedMethod;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ChildOverridesParentWithTrait;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NestedAnonymousClassMethod;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\TwoClassesFirst;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\TwoClassesSecond;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UsesClassBeforeTraitLabel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UsesInsteadofTraits;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UsesLabelledTrait;
use AbeTwoThree\LaravelTsPublish\Transformers\CoreTransformer;
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

it('locateOwn detects an inherited miss from reflection alone, without ever parsing', function () {
    // The file-name gate rejects an inherited method before parseFile() runs at all.
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

    expect($locator->locateOwn(User::class, 'save'))->toBeNull()
        ->and($parser->calls)->toBe(0);
});

it('memoizes a hit so a repeated lookup never re-parses the file', function () {
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

    expect($locator->locateOwn(PostResource::class, 'toArray'))->not->toBeNull();

    $callsAfterFirstHit = $parser->calls;

    expect($callsAfterFirstHit)->toBeGreaterThan(0)
        ->and($locator->locateOwn(PostResource::class, 'toArray'))->not->toBeNull()
        ->and($parser->calls)->toBe($callsAfterFirstHit);
});

it('memoizes a parsing miss so a repeated lookup never re-parses the file', function () {
    // An abstract method clears the file gate, so the file is parsed and only then rejected for having no
    // body; that null has to be cached as firmly as a hit, or every later lookup re-parses.
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

    expect($locator->locateOwn(CoreTransformer::class, 'transform'))->toBeNull()
        ->and($parser->calls)->toBe(1)
        ->and($locator->locateOwn(CoreTransformer::class, 'transform'))->toBeNull()
        ->and($parser->calls)->toBe(1);
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
    // The trait file's only candidate is the trait's own label(); no disambiguation is needed.
    $locator = new MethodLocator(new AstParser);

    expect($literal($locator->locate(UsesLabelledTrait::class, 'label')))->toBe('from trait');
});

it('locate resolves the outer method, not a same-named one nested in an earlier anonymous class', function () use ($literal) {
    // A recursive AST search sees both label() declarations; only the end-line match is unambiguous.
    $locator = new MethodLocator(new AstParser);

    expect($literal($locator->locate(NestedAnonymousClassMethod::class, 'label')))->toBe('outer');
});

it('locate resolves a trait method over an unrelated class declared earlier in the same file', function () use ($literal) {
    require_once __DIR__.'/Fixtures/ClassBeforeTraitInOneFile.php';
    $locator = new MethodLocator(new AstParser);

    expect($literal($locator->locate(UsesClassBeforeTraitLabel::class, 'label')))->toBe('from trait');
});

it('locate resolves an insteadof-selected trait method over its sibling trait in the same file', function () use ($literal) {
    require_once __DIR__.'/Fixtures/InsteadofTraitsInOneFile.php';
    $locator = new MethodLocator(new AstParser);

    expect($literal($locator->locate(UsesInsteadofTraits::class, 'label')))->toBe('from A');
});

it('locate resolves a trait method PHP prefers over an inherited parent method in the same file', function () use ($literal) {
    require_once __DIR__.'/Fixtures/ParentPlusTraitInOneFile.php';
    $locator = new MethodLocator(new AstParser);

    expect($literal($locator->locate(ChildOverridesParentWithTrait::class, 'label')))->toBe('from trait override');
});
