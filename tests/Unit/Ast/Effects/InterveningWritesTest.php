<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Effects\InterveningWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\InterveningResource;
use PhpParser\Node\Expr\Array_;
use PhpParser\NodeFinder;
use Workbench\App\Models\Post;

/**
 * reach()'s answer for the window of a literal written in InterveningResource's namespace and scope: its first item
 * sends $read, and each later item holds one source of $items.
 *
 * @param  list<string>  $items
 */
function windowReach(array $items, string $read = '$this->is_pinned'): bool
{
    $values = implode(', ', array_map(
        static fn (int $index, string $item): string => "'k{$index}' => {$item}",
        array_keys([$read, ...$items]),
        [$read, ...$items],
    ));
    $source = '<?php namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects; '
        .'use Illuminate\Support\Facades\Gate; use Workbench\App\Http\Resources\UserResource; '
        ."use Workbench\App\Support\PinWriter; return [{$values}];";
    $array = new NodeFinder()->findFirstInstanceOf(new AstParser()->parseSource($source), Array_::class);
    $sent = ReadPath::of(runtimeClassCall($read));
    assert($array instanceof Array_ && $sent instanceof ReadPath);

    return InterveningWrites::reach(
        $array->items,
        $sent,
        new AnalysisScope(new ReflectionClass(InterveningResource::class), Post::class),
    );
}

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
        'a model method that is not known to read' => ['$this->isFeatured()', true],
        'additional(), a framework method that writes the read' => [
            "\$this->additional(['a' => 1])",
            true,
            '$this->additional',
        ],
        'the parent constructor' => ['parent::__construct($this->resource)', true],
        'a dynamic method name' => ['$this->{$name}()', true],
        'eval' => ["eval('')", true],
        'a reading helper' => ['$this->reader()', false],
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
        'a self:: helper that reads' => ['self::reader($this->title)', false],
        'a static helper that reads' => ['self::shout($this->title)', false],
        'whenAppended(), read through its body' => ["\$this->whenAppended('flag')", false],
        'additional(), writing another member' => ["\$this->additional(['a' => 1])", false],
    ];
}

it('tells whether a call between the keys may change the discriminant', function (
    string $call,
    bool $mayWrite,
    string $read = '$this->is_pinned',
) {
    expect(windowReach([$call, "{$read} ? 1 : null"], $read))->toBe($mayWrite);
})->with(interveningCalls());

it('reads the last item for its writes and the arguments it binds by reference, not its calls', function (
    string $last,
    bool $mayWrite,
) {
    expect(windowReach(['$this->title', $last], '$this->pinned'))->toBe($mayWrite);
})->with([
    'a writing call in its arm' => ['$this->pinned ? $this->writer() : null', false],
    'a write in its arm' => ['$this->pinned ? ($this->pinned = false) : null', true],
    'a by-reference argument in its arm' => ['$this->pinned ? $this->clearFlag($this->pinned) : null', true],
]);

// A window's answer is any of its items' answers, the last one's calls left out, whatever the order or the repeats.
it('answers for a window as any of its items does', function (int $seed) {
    mt_srand($seed);
    $calls = array_values(array_filter(interveningCalls(), static fn (array $row): bool => ! isset($row[2])));
    $picked = array_map(static fn (): array => $calls[mt_rand(0, count($calls) - 1)], range(1, mt_rand(1, 4)));

    expect(windowReach([...array_column($picked, 0), '$this->is_pinned ? $this->writer() : null']))
        ->toBe(array_any($picked, static fn (array $row): bool => $row[1]));
})->with(range(1, 30));
