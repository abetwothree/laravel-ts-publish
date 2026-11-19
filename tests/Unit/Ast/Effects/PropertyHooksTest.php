<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Effects\PropertyHooks;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\HookedProperties;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\InheritedHookedProperties;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\InterveningResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\PrivateHookChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\PrivateHookParent;
use Workbench\App\Models\Post;

/**
 * The hooks an access of a `$this` path runs, each as its property's class where it runs on `$this`, else null.
 *
 * @param  list<class-string>  $held
 * @return list<array{class-string|null, string}>
 */
function hooksRun(string $path, bool $writes, array $held, string $runtime = InterveningResource::class, ?string $declaring = null): array
{
    $hooks = new PropertyHooks(new ReflectionClass($runtime), true, $held);
    $read = ReadPath::of(runtimeClassCall($path));
    assert($read instanceof ReadPath);
    $declared = new ReflectionClass($declaring ?? $runtime);

    return array_map(
        static fn (array $run): array => [$run[0]?->getName(), $run[1]],
        $writes ? $hooks->setHooks($read, $declared) : $hooks->getHooks($read, $declared),
    );
}

it('names a property a get hook runs on', function (string $class, string $property, bool $runs) {
    expect(PropertyHooks::runsGetHook(new ReflectionClass($class), $property))->toBe($runs);
})->with([
    'a get hook' => [HookedProperties::class, 'reads', true],
    'a plain property' => [HookedProperties::class, 'plain', false],
    'a set hook only' => [HookedProperties::class, 'writes', false],
    'an inherited get hook' => [InheritedHookedProperties::class, 'reads', true],
    'a property the class lacks' => [HookedProperties::class, 'missing', false],
]);

it('names a property a set hook runs on', function (string $class, string $property, bool $runs) {
    expect(PropertyHooks::runsSetHook(new ReflectionClass($class), $property))->toBe($runs);
})->with([
    'a set hook' => [HookedProperties::class, 'writes', true],
    'a get hook only' => [HookedProperties::class, 'reads', false],
    'a plain property' => [HookedProperties::class, 'plain', false],
    'an inherited set hook' => [InheritedHookedProperties::class, 'writes', true],
    'a property the class lacks' => [HookedProperties::class, 'missing', false],
]);

it('lists the hooks an access of a `$this` path runs, on the class each step reaches', function (
    string $path,
    bool $writes,
    array $runs,
) {
    expect(hooksRun($path, $writes, [Post::class]))->toBe($runs);
})->with([
    'a read of a get-hooked property of $this' => ['$this->unpinning', false, [[InterveningResource::class, 'unpinning']]],
    'a write of a set-hooked property of $this' => ['$this->resetting', true, [[InterveningResource::class, 'resetting']]],
    'a read of another object\'s get-hooked property' => ['$this->settings->reads', false, [[null, 'reads']]],
    'a write of another object\'s set-hooked property' => ['$this->settings->writes', true, [[null, 'writes']]],
    'a read of a set-hooked property' => ['$this->resetting', false, []],
    'a write of a get-hooked property of another object' => ['$this->settings->reads', true, []],
    'a read of a plain property of another object' => ['$this->settings->plain', false, []],
    'a model attribute, read through the resource' => ['$this->is_pinned', false, []],
    'a model attribute, read on the model' => ['$this->resource->is_pinned', false, []],
    'another root' => ['$post->unpinning', false, []],
]);

it('reads an undeclared first step on what `$this->resource` holds, but writes it on the resource', function (
    string $path,
    bool $writes,
    array $runs,
) {
    expect(hooksRun($path, $writes, [HookedProperties::class]))->toBe($runs);
})->with([
    'a read the resource forwards' => ['$this->reads', false, [[null, 'reads']]],
    'a read of the held object' => ['$this->resource->reads', false, [[null, 'reads']]],
    'a write the resource keeps' => ['$this->writes', true, []],
    'a write of the held object' => ['$this->resource->writes', true, [[null, 'writes']]],
]);

it('reads a private hooked property a parent declares only in the parent\'s body', function (string $declaring, array $runs) {
    expect(hooksRun('$this->flag', false, [Post::class], PrivateHookChild::class, $declaring))->toBe($runs);
})->with([
    'the parent\'s body' => [PrivateHookParent::class, [[PrivateHookParent::class, 'flag']]],
    'the child\'s body, which reads the name through the proxy' => [PrivateHookChild::class, []],
]);
