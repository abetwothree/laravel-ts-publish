<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Closure;
use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * The helpers InterveningWritesTest calls between a discriminant's keys: ones that write the pin or only read, ones
 * that call themselves or each other, a chain four levels deep, and ones that return a callback.
 *
 * @mixin Post
 */
class InterveningResource extends InterveningBase
{
    public const array LABELS = ['draft' => 'Draft'];

    public const string WRITER = 'writer';

    public static ?Closure $callback = null;

    public static string $suffix = '!';

    public ?Closure $cb = null;

    public bool $pinned = true;

    public ?HookedProperties $settings = null;

    /** @var HookedProperties|null */
    public $docSettings = null;

    public ?FlagContract $contract = null;

    public ?FinalFlags $finalFlags = null;

    public ?OpenFlags $openFlags = null;

    /** @var OpenFlags|null */
    public $docFlags = null;

    public OpenFlags|FinalFlags|null $eitherFlags = null;

    /** Clears the pin on every read. */
    public bool $unpinning {
        get {
            $this->resource->is_pinned = false;

            return true;
        }
    }

    /** Reads the title on every read. */
    public string $hookedTitle {
        get => (string) $this->title;
    }

    /** Clears the pin on every write. */
    public bool $resetting = false {
        set(bool $value) {
            $this->resource->is_pinned = false;
            $this->resetting = $value;
        }
    }

    /** Keeps what it is given, through a set hook that writes nothing else. */
    public string $trimmed = '' {
        set => trim($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [];
    }

    /**
     * Upper-cases a value, as a static method.
     */
    private static function shout(?string $value): string
    {
        return strtoupper((string) $value);
    }

    /**
     * Runs a closure held in a local, as a static method, which no `$this` reaches.
     */
    private static function staticRunsLocal(): int
    {
        $run = static fn (): int => 1;

        return $run();
    }

    /**
     * Clears the flag it is handed second, as a static method.
     */
    private static function clearAfter(int $unused, bool &$flag): bool
    {
        $flag = false;

        return $unused === 0;
    }

    /**
     * Clears the flag it is handed.
     */
    private function clearFlag(bool &$flag): bool
    {
        $flag = false;

        return true;
    }

    /**
     * Clears the pin on the model.
     */
    private function writer(): bool
    {
        $this->resource->is_pinned = false;

        return true;
    }

    /**
     * Clears the pin through another helper.
     */
    private function writesThroughWriter(): bool
    {
        return $this->writer();
    }

    /**
     * Reads the title, with a suffix.
     */
    private function reader(?string $suffix = null): string
    {
        return $this->title.$suffix;
    }

    /**
     * Clears the pin once it has called itself down to zero.
     */
    private function unpinAfter(int $times): bool
    {
        if ($times > 0) {
            return $this->unpinAfter($times - 1);
        }

        $this->resource->is_pinned = false;

        return true;
    }

    /**
     * Counts down by calling itself, writing nothing.
     */
    private function countDown(int $from): int
    {
        return $from > 0 ? $this->countDown($from - 1) : 0;
    }

    /**
     * Reads the title through pong(), which calls back.
     */
    private function ping(int $times): string
    {
        return $times > 0 ? $this->pong($times - 1) : $this->reader();
    }

    /**
     * Calls ping() back.
     */
    private function pong(int $times): string
    {
        return $this->ping($times);
    }

    /**
     * The first of four helpers, each calling the next; only the last reads.
     */
    private function depthOne(): string
    {
        return $this->depthTwo();
    }

    /**
     * The second of four helpers.
     */
    private function depthTwo(): string
    {
        return $this->depthThree();
    }

    /**
     * The third of four helpers.
     */
    private function depthThree(): string
    {
        return $this->depthFour();
    }

    /**
     * The last of four helpers, which reads.
     */
    private function depthFour(): string
    {
        return (string) $this->title;
    }

    /**
     * Returns the writer as a closure.
     */
    private function writerCallback(): Closure
    {
        return $this->writer(...);
    }

    /**
     * Returns the reader as a closure.
     */
    private function readerCallback(): Closure
    {
        return $this->reader(...);
    }

    /**
     * Returns a value its signature does not type.
     */
    private function untypedCallback()
    {
        return 'label';
    }

    /**
     * Returns the writer as a closure its signature does not type.
     */
    private function untypedWriterCallback()
    {
        return $this->writer(...);
    }

    /**
     * Returns the writer as a closure, where its signature types a post that holds none; no caller runs it.
     */
    private function typedPost(): Post
    {
        return $this->writerCallback();
    }

    /**
     * Names the writer as a `Class::method` string.
     */
    private function callableName(): string
    {
        return self::class.'::writer';
    }

    /**
     * Names the writer as a `[$this, 'method']` array.
     *
     * @return array{self, string}
     */
    private function callableArray(): array
    {
        return [$this, 'writer'];
    }

    /**
     * Returns a label, its signature typing a string.
     */
    private function plainLabel(): string
    {
        return (string) $this->title;
    }

    /**
     * Returns the writer as a closure, whatever it is handed.
     */
    private function writerCallbackFor(int $times): Closure
    {
        return $this->writer(...);
    }

    /**
     * Returns a label held in a local, its signature typing nothing.
     */
    private function untypedLocal()
    {
        $label = (string) $this->title;

        return $label;
    }

    /**
     * Returns a value or a default, its signature typing nothing.
     */
    private function orDefault(?string $value, string $default)
    {
        return $value ?? $default;
    }

    /**
     * Returns a label held in a local, its signature typing a string.
     */
    private function typedLocalLabel(): string
    {
        $label = (string) $this->title;

        return $label;
    }

    /**
     * Returns a label, its signature typing nothing; the closure it builds returns the writer to whoever calls it.
     */
    private function untypedWithInnerReturn()
    {
        $later = function (): Closure {
            return $this->writer(...);
        };

        return 'label';
    }

    /**
     * The first of four untyped helpers, each returning the next one's result; only the last returns a label.
     */
    private function untypedOne()
    {
        return $this->untypedTwo();
    }

    /**
     * The second of four untyped helpers.
     */
    private function untypedTwo()
    {
        return $this->untypedThree();
    }

    /**
     * The third of four untyped helpers.
     */
    private function untypedThree()
    {
        return $this->untypedFour();
    }

    /**
     * The last of four untyped helpers, which returns a label.
     */
    private function untypedFour()
    {
        return 'label';
    }

    /**
     * Returns a label once it has called itself down to zero, its signature typing nothing.
     */
    private function untypedCountDown(int $from)
    {
        return $from > 0 ? $this->untypedCountDown($from - 1) : 'done';
    }

    /**
     * Reads the title through middle(), then through deepOne(), which reaches middle() again, spelled in capitals.
     */
    private function middleTwice(): string
    {
        return $this->middle().$this->deepOne();
    }

    /**
     * Reads the title one level further down.
     */
    private function middle(): string
    {
        return $this->leaf();
    }

    /**
     * Reads the title.
     */
    private function leaf(): string
    {
        return (string) $this->title;
    }

    /**
     * Calls deepTwo().
     */
    private function deepOne(): string
    {
        return $this->deepTwo();
    }

    /**
     * Calls middle() again, spelled in capitals.
     */
    private function deepTwo(): string
    {
        return $this->MIDDLE();
    }

    /**
     * Clears the pin of a post of its own, a local that shares only its name with a read.
     */
    private function clearsOwnPost(): bool
    {
        $post = new Post;
        $post->is_pinned = false;

        return true;
    }

    /**
     * Reads the hooked property that clears the pin.
     */
    private function readsUnpinning(): bool
    {
        return $this->unpinning;
    }

    /**
     * Reads nothing, where the parent's pick() writes.
     */
    private function pick(): bool
    {
        return true;
    }
}
