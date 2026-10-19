<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

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

test('reuses an answer a cycle inside it cut short, since nothing outside it decided where', function () {
    $memo = new AnalysisMemo;
    $runs = 0;
    $compute = function () use ($memo, &$runs): int {
        $runs++;
        $memo->enter('guard');
        $memo->enter('guard');
        $memo->leave('guard');

        return $runs;
    };

    expect([$memo->remember('k', $compute), $memo->remember('k', $compute)])->toBe([1, 1]);
});

test('reuses an answer a held guard cut short only while the same guards are held', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;
    $guarded = function () use ($memo, $counting, $runs): int {
        if ($memo->enter('guard')) {
            $memo->leave('guard');
        }

        return $counting($runs)();
    };

    $memo->enter('guard');
    $whileHeld = [$memo->remember('k', $guarded), $memo->remember('k', $guarded)];
    $memo->leave('guard');

    expect([...$whileHeld, $memo->remember('k', $guarded), $memo->remember('k', $guarded)])->toBe([1, 1, 2, 2]);
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

    // The run made while the guard was held was cut short, so it is kept beside the first answer, not in its place.
    expect([$first, $whileGuarded, $memo->remember('k', $guarded)])->toBe([1, 2, 1]);
});

test('an answer that reused another is not reused while a guard the other entered is on the stack', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;
    $inner = function () use ($memo): string {
        if ($memo->enter('guard')) {
            $memo->leave('guard');
        }

        return 'inner';
    };
    $outer = function () use ($memo, $counting, $runs, $inner): int {
        $memo->remember('inner', $inner);

        return $counting($runs)();
    };

    $memo->remember('inner', $inner);
    $first = $memo->remember('outer', $outer);
    $memo->enter('guard');
    $whileGuarded = $memo->remember('outer', $outer);
    $memo->leave('guard');

    // The reuse of `inner` handed its guard to `outer`, so the guarded read ran again and was cut short.
    expect([$first, $whileGuarded, $memo->remember('outer', $outer)])->toBe([1, 2, 1]);
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

test('computes an answer again once the published model set changes', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;

    $before = $memo->remember('k', $counting($runs));
    PublishedModelRegistry::register([Post::class]);

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

test('reset drops every answer', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;

    $memo->remember('k', $counting($runs));
    $memo->reset();

    expect($memo->remember('k', $counting($runs)))->toBe(2);
});
