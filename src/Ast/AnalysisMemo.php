<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use Closure;

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
 *     resources: int,
 *     models: int,
 * }
 * @phpstan-type MemoEntry = array{
 *     value: mixed,
 *     footprint: array<string, true>,
 *     held: array<string, true>,
 *     paths: list<string>,
 *     recording: bool,
 *     dropped: int,
 *     resources: int,
 *     models: int,
 * }
 *
 * @internal
 */
final class AnalysisMemo
{
    /** @var array<string, true> guard keys of the analyses on the stack */
    private array $active = [];

    /** @var list<MemoFrame> the answers being computed, innermost last */
    private array $frames = [];

    /** @var array<string, list<MemoEntry>> each key's answers, one per set of its guards held when it was computed */
    private array $entries = [];

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
     * A stored answer for a key when computing it again could not differ, else a fresh one, stored with the guards it
     * found held, since only those can cut a fresh computation short somewhere else.
     *
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $key, Closure $compute): mixed
    {
        foreach ($this->entries[$key] ?? [] as $entry) {
            if ($this->reproducible($entry)) {
                $this->replay($entry);

                /** @var T $value */
                $value = $entry['value'];

                return $value;
            }
        }

        $depth = count($this->frames);
        $this->frames[] = [
            'footprint' => [],
            'held' => $this->active,
            'pathMark' => DependencyRecorder::mark(),
            'recording' => DependencyRecorder::isRecording(),
            'dropped' => DroppedUnionArms::dropped(),
            'resources' => PublishedResourceRegistry::version(),
            'models' => PublishedModelRegistry::version(),
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
    }

    /**
     * Whether computing an answer again now could only reproduce it: nothing it read has changed, and each guard it
     * entered is held now exactly where it was held then, so a fresh run is cut short at the same places.
     *
     * @param  MemoEntry  $entry
     */
    private function reproducible(array $entry): bool
    {
        // An answer worked out while dependencies went unrecorded has none to replay into a recording run.
        if ($entry['resources'] !== PublishedResourceRegistry::version()
            || $entry['models'] !== PublishedModelRegistry::version()
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
     * Store a computed answer with what it recorded, in place of one computed under the same held guards.
     *
     * @param  MemoFrame  $frame
     */
    private function store(string $key, mixed $value, array $frame): void
    {
        $paths = $frame['recording'] ? DependencyRecorder::since($frame['pathMark']) : [];
        $held = array_intersect_key($frame['held'], $frame['footprint']);
        $entry = [
            'value' => $value,
            'footprint' => $frame['footprint'],
            'held' => $held,
            'paths' => array_values(array_unique($paths)),
            'recording' => $frame['recording'],
            'dropped' => DroppedUnionArms::dropped() - $frame['dropped'],
            'resources' => $frame['resources'],
            'models' => $frame['models'],
        ];

        foreach ($this->entries[$key] ?? [] as $index => $stored) {
            // Loose `==`: two held sets are the same whatever order their guards were entered in.
            if ($stored['held'] == $held) {
                $this->entries[$key][$index] = $entry;

                return;
            }
        }

        $this->entries[$key][] = $entry;
    }
}
