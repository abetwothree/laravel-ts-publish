<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use Closure;
use ReflectionClass;

/**
 * Cycle guards and a memo for one publish run, for the analyses that repeat within it. An answer is reused only where
 * computing it again could not differ, and a reuse replays the cache dependencies and dropped union arms it recorded,
 * so the generation cache and the accessor `null` rule see what a fresh run would have shown them.
 *
 * @phpstan-type MemoFrame = array{
 *     footprint: array<string, true>,
 *     held: array<string, true>,
 *     pathMark: int,
 *     recording: bool,
 *     dropped: int,
 *     reentries: int,
 *     resources: int,
 *     models: int,
 *     enums: int,
 * }
 * @phpstan-type MemoEntry = array{
 *     value: mixed,
 *     footprint: array<string, true>,
 *     held: array<string, true>,
 *     paths: list<string>,
 *     recording: bool,
 *     dropped: int,
 *     reentries: int|null,
 *     resources: int,
 *     models: int,
 *     enums: int,
 * }
 *
 * @internal
 */
final class AnalysisMemo
{
    /** The prefix of the marker held for each guard a re-entry lifts, so an answer a cut read shaped tells it apart. */
    private const string LIFTED = 'lifted:';

    /** @var array<string, true> guard keys of the analyses on the stack, in the order they were entered */
    private array $active = [];

    /** @var array<string, true> the guard keys the re-entry under way lifted */
    private array $lifted = [];

    /** @var array<string, true> the guard keys answering a seed in place of their cut, while a fixpoint check reads */
    private array $seeded = [];

    /** How often a read entered a guard a re-entry under way lifted, or reused an answer that entered one. */
    private int $reentries = 0;

    /** How many answers this run computed rather than reused. */
    private int $computations = 0;

    /** @var list<MemoFrame> the answers being computed, innermost last */
    private array $frames = [];

    /** @var array<string, list<MemoEntry>> each key's answers, one per set of its guards held when it was computed */
    private array $entries = [];

    /** @var array<string, list<MemoEntry>> the answers a seed shaped, reused only until the check that seeds ends */
    private array $seededEntries = [];

    /**
     * The key suffix that tells a body's reads for one runtime class apart: none for the class read itself, since that
     * read is the plain one, and `@assumed` where the runtime class is only assumed.
     *
     * @param  ReflectionClass<object>|null  $runtime
     */
    public static function runtimeKey(string $classRead, ?ReflectionClass $runtime, bool $assumed): string
    {
        $runtimeName = $runtime?->getName();

        return ($runtimeName === null || $runtimeName === $classRead ? '' : '@runtime:'.$runtimeName).($assumed ? '@assumed' : '');
    }

    /**
     * Enter a guarded analysis. Entering one already on the stack returns false instead, which cuts every answer being
     * computed short, so each is reused only while the same guards are held.
     */
    public function enter(string $key): bool
    {
        $innermost = array_key_last($this->frames);

        if ($innermost !== null) {
            $this->frames[$innermost]['footprint'][$key] = true;
        }

        if (isset($this->lifted[$key])) {
            $this->reentries++;
        }

        if (isset($this->active[$key])) {
            return false;
        }

        $this->active[$key] = true;

        return true;
    }

    /**
     * Leave a guarded analysis entered with enter().
     */
    public function leave(string $key): void
    {
        unset($this->active[$key]);
    }

    /**
     * Run a read with each guard entered before `$key`, which the caller holds, lifted, so it enters an analysis
     * already under way once more. SubjectPropertyTypeResolver's `typing-property:` guard is that key, so a read of the
     * property inside ends the cycle at the type it held before.
     *
     * It must not nest, since it replaces the lifted set: a property typed inside it reads through cut() instead.
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    public function reenterOnce(string $key, Closure $read): mixed
    {
        $position = array_search($key, array_keys($this->active), true);
        $lifted = $position === false ? [] : array_slice($this->active, 0, $position, true);
        $marks = self::marks($lifted);
        $liftedBefore = $this->lifted;
        $this->active = array_diff_key($this->active, $lifted) + $marks;
        $this->lifted = $lifted;

        try {
            return $read();
        } finally {
            $this->active = $lifted + array_diff_key($this->active, $marks);
            $this->lifted = $liftedBefore;
        }
    }

    /**
     * Run a read with each guard the re-entry under way lifted held again, so an analysis under way is cut short there
     * as it was before the re-entry.
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    public function cut(Closure $read): mixed
    {
        $lifted = $this->lifted;
        $innermost = array_key_last($this->frames);

        // Its answer holds a lifted guard where a fresh read in which that guard is absent would enter it.
        if ($innermost !== null) {
            $this->frames[$innermost]['footprint'] += self::marks($lifted);
        }

        $held = array_diff_key($lifted, $this->active);
        $this->active += $held;
        $this->lifted = [];

        try {
            return $read();
        } finally {
            $this->active = array_diff_key($this->active, $held);
            $this->lifted = $lifted;
        }
    }

    /**
     * How often a read has entered a guard a re-entry under way lifted, or reused an answer that entered one; a caller
     * compares two readings to tell whether a read went through an analysis already under way.
     */
    public function reentries(): int
    {
        return $this->reentries;
    }

    /**
     * Run a read while the guard `$key`, which the caller holds, answers a seed in place of its cut, as
     * SubjectPropertyTypeResolver's `typing-property:` guard answers a joined type to check it is a fixpoint. An answer
     * that met `$key` outside it is not reused in it, and one that meets it there is reused only until it ends.
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    public function seeding(string $key, Closure $read): mixed
    {
        $seeded = $this->seeded;
        $this->seeded[$key] = true;

        try {
            return $read();
        } finally {
            $this->seeded = $seeded;
            $this->seededEntries = $seeded === [] ? [] : $this->seededEntries;
        }
    }

    /**
     * A stored answer for a key when computing it again could not differ, else a fresh one, stored with the guards it
     * found held, since only those can cut a fresh computation short somewhere else; one a seed shaped is kept apart.
     *
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $key, Closure $compute): mixed
    {
        $enums = PublishedEnums::version();

        $stored = $this->seeded === [] ? $this->entries[$key] ?? [] : [
            ...array_filter($this->entries[$key] ?? [], fn (array $entry): bool => ! $this->seeds($entry['footprint'])),
            ...$this->seededEntries[$key] ?? [],
        ];

        foreach ($stored as $entry) {
            if ($this->reproducible($entry, $enums)) {
                $this->replay($entry);

                /** @var T $value */
                $value = $entry['value'];

                return $value;
            }
        }

        $this->computations++;
        $depth = count($this->frames);
        $this->frames[] = [
            'footprint' => [],
            'held' => $this->active,
            'pathMark' => DependencyRecorder::mark(),
            'recording' => DependencyRecorder::isRecording(),
            'dropped' => DroppedUnionArms::dropped(),
            'reentries' => $this->reentries,
            'resources' => PublishedResourceRegistry::version(),
            'models' => PublishedModelRegistry::version(),
            'enums' => $enums,
        ];

        try {
            $value = $compute();
        } finally {
            $frame = $this->closeFrame($depth);
        }

        $this->store($key, $value, $frame);

        return $value;
    }

    /**
     * Drop every answer, when a run starts or an input they read changes.
     */
    public function reset(): void
    {
        $this->entries = [];
        $this->seededEntries = [];
        $this->computations = 0;
    }

    /**
     * How many answers this run has computed rather than reused, the work a publish does that a test can bound.
     */
    public function computations(): int
    {
        return $this->computations;
    }

    /**
     * The marker of each guard in $keys, which a re-entry holds while it lifts the guard.
     *
     * @param  array<string, true>  $keys
     * @return array<string, true>
     */
    private static function marks(array $keys): array
    {
        $marks = [];

        foreach (array_keys($keys) as $key) {
            $marks[self::LIFTED.$key] = true;
        }

        return $marks;
    }

    /**
     * Whether computing an answer again now could only reproduce it: nothing it read has changed, the published enums
     * at version $enums included, and each guard it entered is held now exactly where it was held then.
     *
     * @param  MemoEntry  $entry
     */
    private function reproducible(array $entry, int $enums): bool
    {
        // An answer worked out while dependencies went unrecorded has none to replay into a recording run.
        if ($entry['resources'] !== PublishedResourceRegistry::version()
            || $entry['models'] !== PublishedModelRegistry::version()
            || $entry['enums'] !== $enums
            || (! $entry['recording'] && DependencyRecorder::isRecording())) {
            return false;
        }

        foreach (array_keys($entry['footprint']) as $key) {
            if (isset($this->active[$key]) !== isset($entry['held'][$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Record what a reused answer recorded when it was computed, handing its guards to the answer around it.
     *
     * @param  MemoEntry  $entry
     */
    private function replay(array $entry): void
    {
        foreach ($entry['paths'] as $path) {
            DependencyRecorder::record($path);
        }

        DroppedUnionArms::replay($entry['dropped']);
        $this->reentries += $entry['reentries'] ?? count(array_intersect_key($entry['footprint'], $this->lifted));

        $innermost = array_key_last($this->frames);

        if ($innermost !== null) {
            $this->frames[$innermost]['footprint'] += $entry['footprint'];
        }
    }

    /**
     * Pop the frame opened at a depth, handing the guards it entered to the frame around it.
     *
     * @return MemoFrame
     */
    private function closeFrame(int $depth): array
    {
        $frame = $this->frames[$depth];
        $this->frames = array_slice($this->frames, 0, $depth);
        $innermost = array_key_last($this->frames);

        if ($innermost !== null) {
            $this->frames[$innermost]['footprint'] += $frame['footprint'];
        }

        return $frame;
    }

    /**
     * Store a computed answer with what it recorded, in place of one computed under the same held guards; one a seed
     * shaped goes with the seeded answers.
     *
     * @param  MemoFrame  $frame
     */
    private function store(string $key, mixed $value, array $frame): void
    {
        $paths = $frame['recording'] ? DependencyRecorder::since($frame['pathMark']) : [];
        $held = array_intersect_key($frame['held'], $frame['footprint']);
        // A guard a cut read entered held is in its footprint uncounted, so such an answer replays its own count.
        $cut = array_any(array_keys($frame['footprint']), fn (string $key): bool => str_starts_with($key, self::LIFTED));
        $entry = [
            'value' => $value,
            'footprint' => $frame['footprint'],
            'held' => $held,
            'paths' => array_values(array_unique($paths)),
            'recording' => $frame['recording'],
            'dropped' => DroppedUnionArms::dropped() - $frame['dropped'],
            'reentries' => $cut ? $this->reentries - $frame['reentries'] : null,
            'resources' => $frame['resources'],
            'models' => $frame['models'],
            'enums' => $frame['enums'],
        ];

        if ($this->seeds($frame['footprint'])) {
            $this->seededEntries[$key] = $this->replaced($this->seededEntries[$key] ?? [], $entry);
        } else {
            $this->entries[$key] = $this->replaced($this->entries[$key] ?? [], $entry);
        }
    }

    /**
     * A key's answers with one computed under the same held guards replaced by $entry, or $entry added.
     *
     * @param  list<MemoEntry>  $entries
     * @param  MemoEntry  $entry
     * @return list<MemoEntry>
     */
    private function replaced(array $entries, array $entry): array
    {
        foreach ($entries as $index => $stored) {
            // Loose `==`: two held sets are the same whatever order their guards were entered in.
            if ($stored['held'] === $entry['held']) {
                $entries[$index] = $entry;

                return $entries;
            }
        }

        return [...$entries, $entry];
    }

    /**
     * Whether an answer that entered the guards of $footprint met one a check has seeded.
     *
     * @param  array<string, true>  $footprint
     */
    private function seeds(array $footprint): bool
    {
        return array_intersect_key($footprint, $this->seeded) !== [];
    }
}
