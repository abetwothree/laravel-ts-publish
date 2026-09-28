<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use Closure;

/**
 * Cycle guards and a memo for one publish run, for the analyses that repeat within it. An answer is reused only where
 * computing it again could not differ, and a reuse replays the cache dependencies and dropped union arms it recorded,
 * so the generation cache and the accessor `null` rule see what a fresh run would have shown them.
 *
 * @phpstan-type MemoFrame = array{
 *     footprint: array<string, true>,
 *     cut: bool,
 *     pathMark: int,
 *     recording: bool,
 *     dropped: int,
 *     resources: int,
 * }
 * @phpstan-type MemoEntry = array{
 *     value: mixed,
 *     footprint: array<string, true>,
 *     paths: list<string>,
 *     recording: bool,
 *     dropped: int,
 *     resources: int,
 *     stamp: int,
 *     pinned: bool,
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

    /** @var array<string, MemoEntry> */
    private array $entries = [];

    /** @var array<string, int> the stamp at which a fresh computation first pinned each key */
    private array $pinnedAt = [];

    private int $clock = 0;

    /**
     * Enter a guarded analysis. Entering one already on the stack returns false instead, and leaves every answer being
     * computed cut short, so none of them is stored.
     */
    public function enter(string $key): bool
    {
        $innermost = array_key_last($this->frames);

        if ($innermost !== null) {
            $this->frames[$innermost]['footprint'][$key] = true;
        }

        if (isset($this->active[$key])) {
            foreach (array_keys($this->frames) as $index) {
                $this->frames[$index]['cut'] = true;
            }

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
     * The stored answer for a key when computing it again could not differ, else a fresh one, stored unless cut short.
     *
     * A pinned answer is AstEngine's outermost analysis in its chain: stored even when cut short and then reused
     * whatever is on the stack, as it always was. An unpinned answer reused by a pinned call is pinned from then on.
     *
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $key, Closure $compute, bool $pin = false): mixed
    {
        $entry = $this->entries[$key] ?? null;

        if ($entry !== null && ($entry['pinned'] || $this->reproducible($entry))) {
            $this->replay($entry);
            $this->entries[$key]['pinned'] = $entry['pinned'] || $pin;

            /** @var T $value */
            $value = $entry['value'];

            return $value;
        }

        $depth = count($this->frames);
        $this->frames[] = [
            'footprint' => [],
            'cut' => false,
            'pathMark' => DependencyRecorder::mark(),
            'recording' => DependencyRecorder::isRecording(),
            'dropped' => DroppedUnionArms::dropped(),
            'resources' => PublishedResourceRegistry::version(),
        ];

        try {
            $value = $compute();
        } finally {
            $frame = $this->closeFrame($depth);
        }

        if ($pin || ! $frame['cut']) {
            $this->store($key, $value, $frame, $pin);
        }

        return $value;
    }

    /**
     * Drop every answer not pinned, when a run starts or an input they read changes.
     */
    public function forget(): void
    {
        $this->entries = array_filter($this->entries, fn (array $entry): bool => $entry['pinned']);
    }

    /**
     * Whether computing an unpinned answer again now could only reproduce it: nothing it read has changed, and no guard
     * it entered is on the stack to cut a fresh run short.
     *
     * @param  MemoEntry  $entry
     */
    private function reproducible(array $entry): bool
    {
        // An answer worked out while dependencies went unrecorded has none to replay into a recording run.
        if ($entry['resources'] !== PublishedResourceRegistry::version()
            || (! $entry['recording'] && DependencyRecorder::isRecording())) {
            return false;
        }

        foreach (array_keys($entry['footprint']) as $key) {
            if (isset($this->active[$key]) || ($this->pinnedAt[$key] ?? 0) > $entry['stamp']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Record what a reused answer recorded when it was computed; a pinned one replays its dependencies only, as a
     * pinned reuse never did more.
     *
     * @param  MemoEntry  $entry
     */
    private function replay(array $entry): void
    {
        foreach ($entry['paths'] as $path) {
            DependencyRecorder::record($path);
        }

        if ($entry['pinned']) {
            return;
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
     * Store a computed answer with what it recorded.
     *
     * @param  MemoFrame  $frame
     */
    private function store(string $key, mixed $value, array $frame, bool $pin): void
    {
        $paths = $frame['recording'] ? DependencyRecorder::since($frame['pathMark']) : [];

        $this->entries[$key] = [
            'value' => $value,
            'footprint' => $frame['footprint'],
            'paths' => array_values(array_unique($paths)),
            'recording' => $frame['recording'],
            'dropped' => DroppedUnionArms::dropped() - $frame['dropped'],
            'resources' => $frame['resources'],
            'stamp' => ++$this->clock,
            'pinned' => $pin,
        ];

        if ($pin) {
            $this->pinnedAt[$key] ??= $this->clock;
        }
    }
}
