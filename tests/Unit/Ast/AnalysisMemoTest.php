<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use Workbench\App\Http\Resources\PostResource;

/**
 * A computation that counts its runs and answers which run it was.
 *
 * @param  ArrayObject<int, int>  $runs
 * @return Closure(): int
 */
$counting = fn (ArrayObject $runs): Closure => function () use ($runs): int {
    $runs[] = count($runs) + 1;

    return count($runs);
};

test('reuses a stored answer instead of computing it again', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;

    expect([$memo->remember('k', $counting($runs)), $memo->remember('k', $counting($runs))])->toBe([1, 1])
        ->and(count($runs))->toBe(1);
});

test('does not store an answer a cycle cut short, since it depends on the chain that reached it', function () {
    $memo = new AnalysisMemo;
    $runs = 0;
    $compute = function () use ($memo, &$runs): int {
        $runs++;
        $memo->enter('guard');
        $memo->enter('guard');
        $memo->leave('guard');

        return $runs;
    };

    expect([$memo->remember('k', $compute), $memo->remember('k', $compute)])->toBe([1, 2]);
});

test('does not reuse an answer while a guard it entered is on the stack', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;
    $guarded = function () use ($memo, $counting, $runs): int {
        if ($memo->enter('guard')) {
            $memo->leave('guard');
        }

        return $counting($runs)();
    };

    $first = $memo->remember('k', $guarded);
    $memo->enter('guard');
    $whileGuarded = $memo->remember('k', $guarded);
    $memo->leave('guard');

    // The run made while the guard was held was cut short, so the first answer is still the stored one.
    expect([$first, $whileGuarded, $memo->remember('k', $guarded)])->toBe([1, 2, 1]);
});

test('keeps a pinned answer a cycle cut short, and reuses it while its guard is on the stack', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;
    $cut = function () use ($memo, $counting, $runs): int {
        $memo->enter('guard');
        $memo->enter('guard');
        $memo->leave('guard');

        return $counting($runs)();
    };

    $first = $memo->remember('k', $cut, pin: true);
    $memo->enter('guard');
    $whileGuarded = $memo->remember('k', $cut);
    $memo->leave('guard');

    expect([$first, $whileGuarded])->toBe([1, 1]);
});

test('replays the dependencies and dropped union arms of a reused answer', function () {
    $memo = new AnalysisMemo;
    $compute = function (): string {
        DependencyRecorder::record('/app/Summary.php');
        DroppedUnionArms::replay(2);

        return 'summary';
    };

    DependencyRecorder::start();
    $memo->remember('k', $compute);
    DependencyRecorder::start();
    $dropped = DroppedUnionArms::dropped();
    $memo->remember('k', $compute);
    $paths = DependencyRecorder::paths();
    DependencyRecorder::stop();

    expect($paths)->toBe(['/app/Summary.php'])
        ->and(DroppedUnionArms::dropped() - $dropped)->toBe(2);
});

test('computes an answer again once the published resource set changes', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;

    $before = $memo->remember('k', $counting($runs));
    PublishedResourceRegistry::register([PostResource::class]);

    expect([$before, $memo->remember('k', $counting($runs))])->toBe([1, 2]);
});

test('computes an answer again when it was worked out without recording the dependencies a reuse now needs', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;

    $unrecorded = $memo->remember('k', $counting($runs));
    DependencyRecorder::start();
    $recorded = $memo->remember('k', $counting($runs));
    DependencyRecorder::stop();

    expect([$unrecorded, $recorded, $memo->remember('k', $counting($runs))])->toBe([1, 2, 2]);
});

test('does not reuse an answer that read an analysis since pinned to a fresh result', function () use ($counting) {
    $memo = new AnalysisMemo;
    $outerRuns = new ArrayObject;
    $innerRuns = new ArrayObject;
    $inner = function () use ($memo, $counting, $innerRuns): int {
        if ($memo->enter('inner')) {
            $memo->leave('inner');
        }

        return $counting($innerRuns)();
    };
    $outer = function () use ($memo, $counting, $outerRuns, $inner): int {
        $memo->remember('inner', $inner);

        return $counting($outerRuns)();
    };

    $memo->remember('outer', $outer);
    $memo->enter('inner');
    $memo->remember('inner', $inner, pin: true);
    $memo->leave('inner');

    expect($memo->remember('outer', $outer))->toBe(2)
        ->and(count($innerRuns))->toBe(2);
});

test('forget drops every answer but the pinned ones', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;

    $memo->remember('pinned', fn (): string => 'kept', pin: true);
    $memo->remember('k', $counting($runs));
    $memo->forget();

    expect($memo->remember('pinned', fn (): string => 'recomputed'))->toBe('kept')
        ->and($memo->remember('k', $counting($runs)))->toBe(2);
});
