<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Effects\PropertyHooks;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\FinalFlags;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\HookedProperties;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\InheritedHookedProperties;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\InterveningResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\MutatingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\OpenFlags;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\PrivateHookChild;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\PrivateHookParent;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\ReferenceGetResource;
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
    'a read of another object\'s get-hooked property, its holder typed by a docblock' => [
        '$this->docSettings->reads',
        false,
        [[null, 'reads']],
    ],
    'a read of a property an interface types, whose hook only an implementer runs' => ['$this->contract->flag', false, []],
    'a write of a property an interface types, whose hook only an implementer runs' => ['$this->contract->label', true, []],
]);

// PHP 8.4 lets a child class hook an inherited public property that is neither final nor readonly, and an object an
// interface types be of any class, so only `$this`, the class published, is read on its own declaration.
it('tells a read that may run a get hook, on the class a step reads or one the object there may be', function (
    string $path,
    array $held,
    bool $runs,
) {
    $read = ReadPath::of(runtimeClassCall($path));
    assert($read instanceof ReadPath);
    $resource = new ReflectionClass(InterveningResource::class);

    expect(new PropertyHooks($resource, true, $held)->mayRunGetHook($read, $resource))->toBe($runs);
})->with([
    'a property an interface types' => ['$this->contract->flag', [Post::class], true],
    'a property an interface does not name, which an implementer may declare' => ['$this->contract->other', [Post::class], true],
    'a plain property of a non-final class' => ['$this->openFlags->open', [Post::class], true],
    'a plain property of a non-final class, its holder typed by a docblock' => ['$this->docFlags->open', [Post::class], true],
    'a plain property of a non-final class a union names' => ['$this->eitherFlags->open', [Post::class], true],
    'a plain property of a non-final class past a final class' => ['$this->finalFlags->inner->open', [Post::class], true],
    'a plain property of a non-final class the resource holds, read through it' => ['$this->plain', [HookedProperties::class], true],
    'a plain property of a non-final class the resource holds, read on it' => [
        '$this->resource->plain',
        [HookedProperties::class],
        true,
    ],
    'a get hook another object declares' => ['$this->settings->reads', [Post::class], true],
    'a get hook of $this' => ['$this->unpinning', [Post::class], true],
    'a property of a final class' => ['$this->finalFlags->open', [Post::class], false],
    'a readonly property of a non-final class' => ['$this->openFlags->frozen', [Post::class], false],
    'a final property of a non-final class' => ['$this->openFlags->fixed', [Post::class], false],
    'a plain property of $this, the class published' => ['$this->pinned', [Post::class], false],
    'a property of a final class the resource holds' => ['$this->open', [FinalFlags::class], false],
    'a model attribute, read through the resource' => ['$this->is_pinned', [Post::class], false],
    'a model attribute, read on the model' => ['$this->resource->is_pinned', [Post::class], false],
    'a model attribute named like a protected property of Model, read through the resource' => ['$this->visible', [Post::class], false],
    'a model attribute named like a protected property of Model, read on the model' => [
        '$this->resource->hidden',
        [Post::class],
        false,
    ],
    'a private property of a non-final class, which a read from outside reaches through `__get()`' => [
        '$this->openFlags->secret',
        [Post::class],
        false,
    ],
    'another root' => ['$post->open', [Post::class], false],
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

it('lists the set mutator a write of a model attribute runs, whose body runs on the model', function (
    string $path,
    array $runs,
) {
    expect(hooksRun($path, true, [MutatingPost::class]))->toBe($runs);
})->with([
    'a setXAttribute() mutator' => ['$this->resource->pin_reset', [[null, 'pin_reset']]],
    'an Attribute with a setter' => ['$this->resource->pin_note', [[null, 'pin_note']]],
    'an Attribute with a setter unpacked by name' => ['$this->resource->pin_mark', [[null, 'pin_mark']]],
    'an Attribute built from an array a helper returns' => ['$this->resource->pin_tag', [[null, 'pin_tag']]],
    'an Attribute with a getter only' => ['$this->resource->label', []],
    'an attribute with no mutator' => ['$this->resource->title', []],
    'an attribute written on the resource, which keeps it' => ['$this->pin_note', []],
]);

it('reads a private hooked property a parent declares only in the parent\'s body', function (string $declaring, array $runs) {
    expect(hooksRun('$this->flag', false, [Post::class], PrivateHookChild::class, $declaring))->toBe($runs);
})->with([
    'the parent\'s body' => [PrivateHookParent::class, [[PrivateHookParent::class, 'flag']]],
    'the child\'s body, which reads the name through the proxy' => [PrivateHookChild::class, []],
]);

// JsonResource's `__get()` returns by value, so a by-reference parameter binds a copy of what it forwards.
it('tells a read that starts at a declared property, which a by-reference parameter binds in place', function (
    string $path,
    array $held,
    bool $inPlace,
    string $resource = InterveningResource::class,
) {
    $read = ReadPath::of(runtimeClassCall($path));
    assert($read instanceof ReadPath);
    $class = new ReflectionClass($resource);

    expect(new PropertyHooks($class, true, $held)->bindsInPlace($read, $class))->toBe($inPlace);
})->with([
    'a property the resource declares' => ['$this->pinned', [Post::class], true],
    'a model attribute, read through the resource' => ['$this->is_pinned', [Post::class], false],
    'a model attribute, read on the model' => ['$this->resource->is_pinned', [Post::class], false],
    'a property the held object declares, read through the resource' => ['$this->plain', [HookedProperties::class], false],
    'a property the held object declares, read through a `__get()` returning by reference' => [
        '$this->plain',
        [HookedProperties::class],
        true,
        ReferenceGetResource::class,
    ],
    'a property the held object declares, read on it' => ['$this->resource->plain', [HookedProperties::class], true],
    'another root' => ['$post->is_pinned', [Post::class], false],
]);

it('names the classes the object a path reads may be', function (string $path, array $classes) {
    $read = ReadPath::of(runtimeClassCall($path));
    assert($read instanceof ReadPath);
    $resource = new ReflectionClass(InterveningResource::class);

    expect(array_map(
        static fn (ReflectionClass $class): string => $class->getName(),
        new PropertyHooks($resource, true, [Post::class])->classesOf($read, $resource),
    ))->toBe($classes);
})->with([
    'the model the resource holds' => ['$this->resource', [Post::class]],
    'a property the resource types' => ['$this->finalFlags', [FinalFlags::class]],
    'a property a step further' => ['$this->finalFlags->inner', [OpenFlags::class]],
    'a relation the model types no property for' => ['$this->author', []],
    'another root' => ['$post->author', []],
]);
