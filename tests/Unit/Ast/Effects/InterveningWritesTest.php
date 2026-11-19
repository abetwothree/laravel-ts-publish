<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\InterveningResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\InterveningSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\MutatingPost;

/**
 * The calls between a discriminant and the key that tests it, each with whether it may change the discriminant's read.
 *
 * @return array<string, array{string, bool, 2?: string}>
 */
function interveningCalls(): array
{
    return [
        'a by-reference argument bound to the read' => ['$this->clearFlag($this->pinned)', true, '$this->pinned'],
        'a by-reference argument bound to the model\'s attribute' => [
            '$this->clearFlag($this->resource->is_pinned)',
            true,
        ],
        'the read handed to a method of an object no scan names' => ['$service->reset($this->pinned)', true, '$this->pinned'],
        'the read handed to a method of a built object' => ['(new PinWriter)->reset($this->pinned)', true, '$this->pinned'],
        'the read handed to a method of what a helper returns' => ['$this->reader()->reset($this->pinned)', true, '$this->pinned'],
        'data_set() handed the model' => ["data_set(\$this->resource, 'is_pinned', false)", true],
        'forceFill() on the model' => ["\$this->resource->forceFill(['is_pinned' => false])", true],
        'save() on the model' => ['$this->resource->save()', true],
        'a helper that writes' => ['$this->writer()', true],
        'a helper calling a helper that writes' => ['$this->writesThroughWriter()', true],
        'a chain four helpers deep' => ['$this->depthOne()', true],
        'a self-recursive helper that writes' => ['$this->unpinAfter(3)', true],
        'a writer\'s callable when() calls' => ['$this->when(true, $this->writer(...))', true],
        'an arrow function when() calls' => ['$this->when(true, fn () => $this->writer())', true],
        'a local when() may call' => ['$this->when(true, $callback)', true],
        'a closure call' => ['$f()', true],
        'a self:: helper that writes' => ['self::writer()', true],
        'another class\'s static method handed the model' => ['PinWriter::unpin($this->resource)', true],
        'the model handed to a helper' => ['$this->reader($this->resource)', true],
        'a resource whose own constructor writes the model' => ['new UnpinningResource($this->resource)', true],
        'make() of a resource whose own constructor writes the model' => ['UnpinningResource::make($this->resource)', true],
        'collection() of a resource whose own constructor writes the model' => [
            'UnpinningResource::collection([$this->resource])',
            true,
        ],
        'a model method that is not known to read' => ['$this->isFeatured()', true],
        'a resource of the model, resolved inside the literal' => ['(new UserResource($this->resource))->resolve()', true],
        'make() of a resource of the model, resolved' => ['UserResource::make($this->resource)->resolve()', true],
        'a resource of the model, turned into an array' => ['(new UserResource($this->resource))->toArray(request())', true],
        'collection() of the model, resolved' => ['UserResource::collection([$this->resource])->resolve()', true],
        'a resource of the model, handed more data' => ["UserResource::make(\$this->resource)->additional(['a' => 1])", true],
        'a resource of the model, resolved through ?->' => ['(new UserResource($this->resource))?->jsonSerialize()', true],
        'additional(), a framework method that writes the read' => [
            "\$this->additional(['a' => 1])",
            true,
            '$this->additional',
        ],
        'the parent constructor' => ['parent::__construct($this->resource)', true],
        'a dynamic method name' => ['$this->{$name}()', true],
        'eval' => ["eval('')", true],
        'include' => ["include 'missing.php'", true],
        'call_user_func() handed a function\'s name' => ["call_user_func('strtoupper', 'a')", true],
        'call_user_func() handed a callable array' => ["call_user_func([\$this, 'reader'])", true],
        'a static method of a class held in a local' => ['$class::make()', true],
        'a dynamic method name through ?->' => ['$this?->{$name}()', true],
        'a method of an object a computed member holds' => ['$this->{$name}->save()', true],
        'an unpacked argument to when()' => ['$this->when(true, ...$callbacks)', true],
        'a ternary callback, one branch a writer' => ['$this->when(true, $flag ? $this->reader(...) : $this->writer(...))', true],
        'a coalesce callback, one side a local' => ['$this->when(true, $callback ?? $this->reader(...))', true],
        'a match callback, one arm a writer' => ['$this->when(true, match ($flag) { 1 => $this->writer(...), default => null })', true],
        'a static property callback, the accepted gap' => ['$this->when(true, self::$callback)', false],
        'an assignment callback of a writer' => ['$this->when(true, $f = $this->writer(...))', true],
        'a callback a helper returns, typed Closure' => ['$this->when(true, $this->writerCallback())', true],
        'a callback an untyped helper returns, read through its returns' => [
            '$this->when(true, $this->untypedWriterCallback())',
            true,
        ],
        'an array holding the model' => ['collect([$this->resource])', true],
        'a ternary yielding the model' => ['collect($flag ? $this->resource : null)', true],
        'a coalesce yielding the model' => ['collect($this->resource ?? null)', true],
        'an assignment of the model' => ['collect($model = $this->resource)', true],
        'a match yielding the model' => ['collect(match (true) { default => $this->resource })', true],
        'an arrow function returning the model' => ['collect(fn () => $this->resource)', true],
        'a closure returning the model' => ['collect(function () { return $this->resource; })', true],
        'a writer\'s callable handed to array_map()' => ['array_map($this->writer(...), [1])', true],
        'a self:: writer\'s callable handed to array_map()' => ['array_map(self::writer(...), [1])', true],
        'a writer\'s callable invoked' => ['$this->writer(...)->__invoke()', true],
        'a local invoked' => ['$callback->__invoke()', true],
        'a writer named by \'static::\'' => ["array_map('static::writer', [1])", true],
        'a writer named by [\'parent\', …]' => ["array_map(['parent', 'baseWriter'], [1])", true],
        'a writer named by [$this, …]' => ["array_map([\$this, 'writer'], [1])", true],
        'a method of $this named by a local' => ['array_map([$this, $name], [1])', true],
        'a writer named by [self::class, …]' => ['array_map([self::class, \'writer\'], [1])', true],
        'a writer named by [static::class, …]' => ['array_map([static::class, \'writer\'], [1])', true],
        'a writer named by [Ancestor::class, …]' => ['array_map([InterveningBase::class, \'baseWriter\'], [1])', true],
        'a writer named by [\'Fqcn\', …]' => ['array_map([\''.InterveningResource::class.'\', \'writer\'], [1])', true],
        'a model reader\'s name on an unrelated class' => ['array_map([PinWriter::class, \'getKey\'], [1])', false],
        'a coalescing assignment of a value to a local callback' => ["\$this->when(true, \$callback ??= 'none')", true],
        'Closure::fromCallable() of a local callback' => ['$this->when(true, Closure::fromCallable($callback))', true],
        'a writer named by \'Fqcn::m\'' => ['array_map(\''.InterveningResource::class.'::writer\', [1])', true],
        'a writer named by self::class.\'::m\'' => ['array_map(self::class.\'::writer\', [1])', true],
        'a writer named by a computed self::class callable' => ['array_map(self::class.\'::\'.$name, [1])', true],
        'a writer through Closure::fromCallable([self::class, …])' => [
            'array_map(Closure::fromCallable([self::class, \'writer\']), [1])',
            true,
        ],
        'a closure a helper returns, handed to array_map()' => ['array_map($this->writerCallback(), [1])', true],
        'a closure a helper returns, handed to a collection\'s map()' => [
            'collect([1])->map($this->writerCallback())->all()',
            true,
        ],
        'a pipe into a writer\'s callable' => ['1 |> $this->writer(...)', true],
        'a pipe of the model into another class\'s static method' => ['$this->resource |> PinWriter::unpin(...)', true],
        'a pipe of the model into an arrow function' => ['$this->resource |> (fn ($post) => $post->is_pinned = false)', true],
        'a pipe into a local' => ['$this->title |> $callback', true],
        'the model handed to an invoked static callable' => ['PinWriter::unpin(...)->__invoke($this->resource)', true],
        'an object cast of a local callback' => ['$this->when(true, (object) $callback)', true],
        'a coalescing assignment callback' => ['$this->when(true, $f ??= $this->writer(...))', true],
        'a clone of a local callback' => ['$this->when(true, clone $callback)', true],
        'a forwarded model method callback not known to read' => ['$this->when(true, $this->isFeatured())', true],
        'a model method\'s callable handed to array_map()' => ['array_map($this->resource->touch(...), [1])', true],
        'another class\'s missing static method handed to array_map()' => ['array_map(PinWriter::missing(...), [1])', false],
        'a named ancestor\'s writer' => ['InterveningBase::baseWriter()', true],
        'a parent:: writer' => ['parent::baseWriter()', true],
        'a parent body whose self:: names the parent' => ['$this->viaSelf()', true],
        'a get hook that writes' => ['$this->unpinning', true],
        'a helper reading a get hook that writes' => ['$this->readsUnpinning()', true],
        'a set hook that writes' => ['$this->resetting = true', true],
        'a get hook on another object\'s property, which runs with another $this' => ['$this->settings->reads', true],
        'a set hook on another object\'s property' => ["\$this->settings->writes = 'A'", true],
        'a reading helper' => ['$this->reader()', false],
        'a get hook that only reads' => ['$this->hookedTitle', false],
        'a set hook that writes only its own property' => ["\$this->trimmed = ' a '", false],
        'a plain property of another object' => ["\$this->settings->plain = 'a'", false],
        'a self-recursive helper that reads' => ['$this->countDown(3)', false],
        'helpers that call each other and read' => ['$this->ping(3)', false],
        'a chain three helpers deep that reads' => ['$this->depthTwo()', false],
        'a reader\'s callable when() calls' => ['$this->when(true, $this->reader(...))', false],
        'a writer\'s callable nothing calls' => ['$x = $this->writer(...)', false],
        'a method of the read\'s own enum' => ['$this->status->color()', false, '$this->status'],
        'a method of another read' => ["\$this->created_at->format('Y')", false],
        'a model reader the resource forwards' => ['$this->getKey()', false],
        'a model reader on the model' => ["\$this->resource->relationLoaded('author')", false],
        'route() handed the model' => ["route('posts.show', \$this->resource)", false],
        'a Gate check handed the model' => ["Gate::allows('view', \$this->resource)", false],
        'a resource of the model' => ['new UserResource($this->resource)', false],
        'a resource of a loaded relation' => ["new UserResource(\$this->whenLoaded('author'))", false],
        'a resource of another read, resolved inside the literal' => ['(new UserResource($this->title))->resolve()', false],
        'make() of a resource' => ['UserResource::make($this->resource)', false],
        'collection() of a resource' => ['UserResource::collection([$this->resource])', false],
        'collection() of a loaded relation' => ["UserResource::collection(\$this->whenLoaded('comments'))", false],
        'a self:: helper that reads' => ['self::reader($this->title)', false],
        'a static helper that reads' => ['self::shout($this->title)', false],
        'whenAppended(), read through its body' => ["\$this->whenAppended('flag')", false],
        'additional(), writing another member' => ["\$this->additional(['a' => 1])", false],
        'call_user_func() handed a closure' => ['call_user_func(fn () => $this->reader())', false],
        'a ternary callback of readers and values' => ["\$this->when(true, \$flag ? \$this->reader(...) : 'none')", false],
        'a coalesce callback of a read and a reader' => ['$this->when(true, $this->title ?? $this->reader(...))', false],
        'a match callback of readers' => ['$this->when(true, match ($flag) { 1 => $this->reader(...), default => PHP_EOL })', false],
        'an assignment callback of a reader' => ['$this->when(true, $f = $this->reader(...))', false],
        'a callback a helper returns, typed string' => ['$this->when(true, $this->reader())', false],
        'a static reader\'s callable when() calls' => ['$this->when(true, self::shout(...))', false],
        'a read, an array and a resource handed to when()' => ['$this->when(true, [$this->title, new UserResource($this->resource)])', false],
        'a reader\'s callable handed to array_map()' => ["array_map(\$this->reader(...), ['a'])", false],
        'a reader\'s callable handed to a collection\'s map()' => ["collect(['a'])->map(\$this->reader(...))->all()", false],
        'a static reader\'s callable handed to array_map()' => ["array_map(self::shout(...), ['a'])", false],
        'a function\'s callable handed to array_map()' => ["array_map(strtoupper(...), ['a'])", false],
        'a model reader\'s callable handed to array_map()' => ["array_map(\$this->resource->getAttribute(...), ['title'])", false],
        'another class\'s static reader handed to array_map()' => ["array_map(\\Illuminate\\Support\\Str::upper(...), ['a'])", false],
        'a reader\'s callable invoked' => ['$this->reader(...)->__invoke()', false],
        'a reader named by \'self::\'' => ["array_map('self::reader', ['a'])", false],
        'a reader named by [$this, …]' => ["array_map([\$this, 'reader'], ['a'])", false],
        'a named ancestor\'s reader' => ['InterveningBase::baseReader()', false],
        'a parent:: reader' => ['parent::baseReader()', false],
        'new self of the subject' => ['new self($this->resource)', false],
        'new static of the subject' => ['new static($this->resource)', false],
        'a helper read once per scan, whatever its case' => ['$this->middleTwice()', false],
        'a callback an untyped helper returns' => ['$this->when(true, $this->untypedCallback())', false],
        'a callback a self-recursive untyped helper returns' => ['$this->when(true, $this->untypedCountDown(3))', false],
        'a function\'s callable when() calls' => ['$this->when(true, strtoupper(...))', false],
        'make() of a resource as a when() value' => ['$this->when(true, UserResource::make($this->resource))', false],
        'make() of a loaded relation as a when() value' => [
            "\$this->when(true, UserResource::make(\$this->whenLoaded('author')))",
            false,
        ],
        'collection() as a when() value' => ['$this->when(true, UserResource::collection([$this->resource]))', false],
        'a concatenation as a when() value' => ["\$this->when(true, \$this->title.' (pinned)')", false],
        'a cast as a when() value' => ['$this->when(true, (string) $this->id)', false],
        'a comparison as a when() value' => ['$this->when(true, $this->id > 5)', false],
        'a negation as a when() value' => ['$this->when(true, ! $this->title)', false],
        'an array read of a property as a when() value' => ["\$this->when(true, \$this->metadata['title'] ?? null)", false],
        'a method of another read as a when() value' => ["\$this->when(true, \$this->created_at?->format('Y-m-d'))", false],
        'another class\'s static method as a when() value' => [
            '$this->when(true, \\Illuminate\\Support\\Str::upper((string) $this->title))',
            false,
        ],
        'a forwarded model reader as a when() value' => ['$this->when(true, $this->getKey())', false],
        'an object cast of a read as a when() value' => ['$this->when(true, (object) $this->title)', false],
        'a reader named by [self::class, …]' => ["array_map([self::class, 'reader'], ['a'])", false],
        'a reader named by [static::class, …]' => ["array_map([static::class, 'reader'], ['a'])", false],
        'a reader named by [Ancestor::class, …]' => ["array_map([InterveningBase::class, 'baseReader'], ['a'])", false],
        'a reader named by [\'Fqcn\', …]' => ["array_map(['".InterveningResource::class."', 'reader'], ['a'])", false],
        'a callback a helper typed string returns from a local' => ['$this->when(true, $this->typedLocalLabel())', false],
        'a callback an untyped helper returns, beside a closure\'s return' => [
            '$this->when(true, $this->untypedWithInnerReturn())',
            false,
        ],
        'a reader named by \'Fqcn::m\'' => ["array_map('".InterveningResource::class."::reader', ['a'])", false],
        'a method a joined self::class string names' => ["array_map(self::class.'::reader', ['a'])", true],
        'a reader through Closure::fromCallable([self::class, …])' => [
            "array_map(Closure::fromCallable([self::class, 'reader']), ['a'])",
            false,
        ],
        'a reader closure a helper returns, handed to array_map()' => ["array_map(\$this->readerCallback(), ['a'])", false],
        'a pipe into a reader\'s callable' => ['1 |> $this->reader(...)', false],
        'a pipe of a read into a function\'s callable' => ['$this->title |> strtoupper(...)', false],
        'a pipe of a read into an arrow function' => ['$this->title |> (fn ($title) => strtoupper($title))', false],
    ];
}

it('tells whether a call between the keys may change the discriminant', function (
    string $call,
    bool $mayWrite,
    string $read = '$this->is_pinned',
) {
    expect(windowReach([$call, "{$read} ? 1 : null"], $read))->toBe($mayWrite);
})->with(interveningCalls());

it('reads each body once a publish run, whatever scan reaches it', function () {
    $memo = resolve(AnalysisMemo::class);
    $window = ['$this->middleTwice()', '$this->is_pinned ? 1 : null'];

    expect(windowReach($window))->toBeFalse();

    $computed = $memo->computations();

    expect(windowReach($window))->toBeFalse()
        ->and($memo->computations())->toBe($computed)
        ->and(array_filter($memo->keys(), static fn (string $key): bool => str_starts_with($key, 'body-reads:')))
        ->toHaveCount(5);
});

// The run's bodies are read once and replayed, so a window answers as a fresh run would after any other window.
it('answers each window alike whatever windows the run read before it', function (int $seed) {
    $rows = array_values(array_filter(interveningCalls(), static fn (array $row): bool => ! isset($row[2])));
    mt_srand($seed);
    shuffle($rows);

    foreach ($rows as [$call, $mayWrite]) {
        expect(windowReach([$call, '$this->is_pinned ? 1 : null']))->toBe($mayWrite, $call);
    }
})->with([1, 2]);

// The window's first item reads untypedTwo() to the end; the returns of untypedOne() start a level further down.
it('reads what a helper returns as deep as the scan reads a body, whatever an earlier item read', function () {
    expect(windowReach(['$this->untypedTwo()', '$this->when(true, $this->untypedOne())', '$this->is_pinned ? 1 : null']))
        ->toBeTrue()
        ->and(windowReach(['$this->when(true, $this->untypedOne())', '$this->is_pinned ? 1 : null']))->toBeTrue()
        ->and(windowReach(['$this->when(true, $this->untypedTwo())', '$this->is_pinned ? 1 : null']))->toBeFalse();
});

it('reads a scope that is no API resource without forwarding to its model', function (string $call, bool $mayWrite) {
    expect(windowReach([$call, '$this->is_pinned ? 1 : null'], '$this->is_pinned', InterveningSubject::class))->toBe($mayWrite);
})->with([
    'a method the subject lacks' => ['$this->getKey()', true],
    'a method its class lacks, named by [self::class, …], with no __call()' => ["array_map([self::class, 'missing'], [1])", false],
    'a write to its own resource property' => ['$this->resource->is_pinned = false', false],
]);

it('reads a discriminant held by a local, which only the window can write', function (string $call, bool $mayWrite) {
    expect(windowReach([$call, '$post->is_pinned ? 1 : null'], '$post->is_pinned'))->toBe($mayWrite);
})->with([
    'a helper writing $this' => ['$this->writer()', false],
    'a helper writing a local of the same name' => ['$this->clearsOwnPost()', false],
    'the local handed to a writer' => ['PinWriter::unpin($post)', true],
    'the local in a pair\'s second slot, handed to a writer (NB6)' => ["PinWriter::unpin(['k', \$post])", true],
    'a read of the local in a pair\'s second slot' => ["PinWriter::unpin(['k', \$post->title])", false],
    'a write through the local' => ['$post->is_pinned = false', true],
]);

it('reads the last item for its writes and the arguments it binds by reference, not its calls', function (
    string $last,
    bool $mayWrite,
) {
    expect(windowReach(['$this->title', $last], '$this->pinned'))->toBe($mayWrite);
})->with([
    'a writing call in its arm' => ['$this->pinned ? $this->writer() : null', false],
    'a get hook that writes, in its arm' => ['$this->pinned ? $this->unpinning : null', false],
    'a write in its arm' => ['$this->pinned ? ($this->pinned = false) : null', true],
    'a by-reference argument in its arm' => ['$this->pinned ? $this->clearFlag($this->pinned) : null', true],
    'the read handed to a method of an object no scan names, in its arm' => ['$this->pinned ? $service->reset($this->pinned) : null', true],
    'the read handed to a method $this lacks, which takes it by value' => ['$this->pinned ? $this->missing($this->pinned) : null', false],
    'another read handed to a method of an object no scan names' => ['$this->pinned ? $service->reset($this->title) : null', false],
]);

// Eloquent's setAttribute() runs an attribute's set mutator, which may set any other attribute.
it('reads a write to a model attribute as a call where a set mutator runs', function (string $write, bool $mayWrite) {
    expect(windowReach([$write, '$this->is_pinned ? 1 : null'], model: MutatingPost::class))->toBe($mayWrite);
})->with([
    'a setXAttribute() mutator' => ['$this->resource->pin_reset = true', true],
    'an Attribute with a setter' => ["\$this->resource->pin_note = 'a'", true],
    'an Attribute a builder the source names no setter in returns' => ["\$this->resource->badge = 'a'", true],
    'a mutator, through ??=' => ["\$this->resource->pin_note ??= 'a'", true],
    'an Attribute with a getter only, cached' => ["\$this->resource->label = 'a'", false],
    'an Attribute whose setter is null' => ["\$this->resource->summary = 'a'", false],
    'an Attribute whose getter is a closure with its own return' => ["\$this->resource->caption = 'a'", false],
    'an attribute with no mutator' => ["\$this->resource->title = 'a'", false],
    'a write the resource keeps, which runs no mutator' => ['$this->pin_reset = true', false],
]);

it('reads a set mutator in the last item\'s arm as a call that runs after its guard', function () {
    expect(windowReach(['$this->title', "\$this->is_pinned ? (\$this->resource->pin_note = 'a') : null"], model: MutatingPost::class))
        ->toBeFalse();
});

// Eloquent reads an attribute through `__get()`, which hands a by-reference parameter a copy of its value.
it('reads a model attribute handed to a method of an object no scan names as bound by value', function (string $read) {
    expect(windowReach(['$this->title', "\$this->is_pinned ? \$service->reset({$read}) : null"]))->toBeFalse();
})->with(['$this->is_pinned', '$this->resource->is_pinned']);

// A window's answer is any of its items' answers, the last one's calls left out, whatever the order or the repeats.
it('answers for a window as any of its items does', function (int $seed) {
    mt_srand($seed);
    $calls = array_values(array_filter(interveningCalls(), static fn (array $row): bool => ! isset($row[2])));
    $picked = array_map(static fn (): array => $calls[mt_rand(0, count($calls) - 1)], range(1, mt_rand(1, 4)));

    expect(windowReach([...array_column($picked, 0), '$this->is_pinned ? $this->writer() : null']))
        ->toBe(array_any($picked, static fn (array $row): bool => $row[1]));
})->with(range(1, 30));
