<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseHelperLevelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SevenHelperLevelResource;
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

test('a read re-entering once enters each guard entered before its key once more, the key held', function () {
    $memo = new AnalysisMemo;
    $read = function () use ($memo): array {
        $entered = [$memo->enter('method'), $memo->enter('method'), $memo->enter('property')];
        $memo->leave('method');

        return $entered;
    };

    $memo->enter('method');
    $memo->enter('property');
    $inside = $memo->reenterOnce('property', $read);

    expect($inside)->toBe([true, false, false])
        ->and([$memo->enter('method'), $memo->enter('property')])->toBe([false, false]);
});

test('a re-entry lifts only the guards entered before its key, and a cut read holds them again', function () {
    $memo = new AnalysisMemo;
    $read = function () use ($memo): array {
        $entered = [$memo->enter('outermost'), $memo->cut(fn (): bool => $memo->enter('method')), $memo->enter('method')];
        $memo->leave('method');

        return $entered;
    };

    $memo->enter('method');
    $memo->enter('property');
    $memo->enter('outermost');

    expect($memo->reenterOnce('property', $read))->toBe([false, false, true])
        ->and([$memo->enter('method'), $memo->enter('property'), $memo->enter('outermost')])->toBe([false, false, false]);
});

test('a re-entry inside one that lifted a guard throws, and the outer one still holds its guards again', function () {
    $memo = new AnalysisMemo;
    $memo->enter('method');
    $memo->enter('property');
    $memo->enter('inner');

    expect(fn (): bool => $memo->reenterOnce('property', fn (): bool => $memo->reenterOnce('inner', fn (): bool => true)))
        ->toThrow(LogicException::class)
        ->and([$memo->enter('method'), $memo->enter('property'), $memo->enter('inner')])->toBe([false, false, false]);
});

test('an answer read while a re-entry lifts a guard is not reused where the guard is held, nor the other way round', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;
    $guarded = function () use ($memo, $counting, $runs): int {
        if ($memo->enter('method')) {
            $memo->leave('method');
        }

        return $counting($runs)();
    };

    $memo->enter('method');
    $memo->enter('property');
    $held = $memo->remember('k', $guarded);
    $lifted = $memo->reenterOnce('property', fn (): array => [$memo->remember('k', $guarded), $memo->remember('k', $guarded)]);

    expect([$held, ...$lifted, $memo->remember('k', $guarded)])->toBe([1, 2, 2, 1]);
});

test('an answer a cut read shaped is reused only where the guards it held again are lifted again', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;
    $cutRead = fn (): int => $memo->cut(function () use ($memo, $counting, $runs): int {
        if ($memo->enter('method')) {
            $memo->leave('method');
        }

        return $counting($runs)();
    });
    $plain = fn (): int => $memo->remember('k', $cutRead);

    $memo->enter('method');
    $memo->enter('property');
    $lifted = $memo->reenterOnce('property', fn (): array => [$plain(), $plain()]);
    $memo->leave('property');
    $memo->leave('method');

    expect([...$lifted, $plain()])->toBe([1, 1, 2]);
});

test('a reused answer a cut read shaped counts the re-entries its fresh computation counted', function () {
    $memo = new AnalysisMemo;
    $enters = function (string $key) use ($memo): string {
        if ($memo->enter($key)) {
            $memo->leave($key);
        }

        return $key;
    };
    $cutOnly = fn (): string => $memo->cut(fn (): string => $enters('method'));
    $outerThenCut = fn (): string => $enters('outer').$memo->cut(fn (): string => $enters('method'));

    $memo->enter('outer');
    $memo->enter('method');
    $memo->enter('property');
    $counts = $memo->reenterOnce('property', function () use ($memo, $cutOnly, $outerThenCut): array {
        $counts = [];

        foreach (['h' => $cutOnly, 'g' => $outerThenCut, 'h2' => $cutOnly, 'g2' => $outerThenCut] as $key => $read) {
            $before = $memo->reentries();
            $memo->remember(rtrim($key, '2'), $read);
            $counts[] = $memo->reentries() - $before;
        }

        return $counts;
    });

    expect($counts)->toBe([0, 1, 0, 1]);
});

test('counts each entry into a guard a re-entry lifted, and each reused answer that entered one, but none in a cut read', function () {
    $memo = new AnalysisMemo;
    $guarded = function () use ($memo): string {
        if ($memo->enter('method')) {
            $memo->leave('method');
        }

        return 'read';
    };

    $memo->remember('k', $guarded);
    $fresh = $memo->reentries();
    $memo->enter('method');
    $memo->enter('property');
    $counts = $memo->reenterOnce('property', function () use ($memo, $guarded): array {
        $before = $memo->reentries();
        $memo->remember('k', $guarded);
        $reused = $memo->reentries();
        $memo->remember('other', $guarded);
        $computed = $memo->reentries();
        $memo->cut(fn (): string => $memo->remember('third', $guarded));

        return [$reused - $before, $computed - $reused, $memo->reentries() - $computed];
    });

    expect([$fresh, ...$counts])->toBe([0, 1, 1, 0]);
});

test('a seeded check reuses no answer that met its guard outside it, and drops those it computed when it ends', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;
    $meets = function () use ($memo, $counting, $runs): int {
        $memo->enter('property');

        return $counting($runs)();
    };
    $plain = new ArrayObject;
    $check = fn (): array => [$memo->remember('met', $meets), $memo->remember('met', $meets), $memo->remember('plain', $counting($plain))];

    $memo->enter('property');
    $before = $memo->remember('met', $meets);
    $seeded = $memo->seeding('property', $check);
    $after = [$memo->remember('met', $meets), $memo->remember('plain', $counting($plain))];

    expect([$before, ...$seeded, ...$after, $memo->seeding('property', $check)[0]])->toBe([1, 2, 2, 1, 1, 1, 3]);
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

// A cut read holds its lifted guards again after the others, so one held set may come in either order.
test('replaces an answer computed under the same held guards entered in another order', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;
    $guarded = function () use ($memo, $counting, $runs): int {
        $memo->enter('method');
        $memo->enter('property');

        return $counting($runs)();
    };

    $memo->enter('method');
    $memo->enter('property');
    $unrecorded = $memo->remember('k', $guarded);
    $memo->leave('method');
    $memo->enter('method');
    DependencyRecorder::start();
    $recorded = $memo->remember('k', $guarded);
    DependencyRecorder::stop();

    expect([$unrecorded, $recorded, $memo->remember('k', $guarded)])->toBe([1, 2, 2]);
});

test('reset drops every answer', function () use ($counting) {
    $memo = new AnalysisMemo;
    $runs = new ArrayObject;

    $memo->remember('k', $counting($runs));
    $memo->reset();

    expect($memo->remember('k', $counting($runs)))->toBe(2);
});

// A call that forwards nothing binds `static` to the class it names, so its read is that class's own, under one key.
test('keys a static call through a class\'s name that forwards nothing as a read of that class itself', function () {
    $memo = resolve(AnalysisMemo::class);
    $memo->reset();
    $published = publishedResourceKeyTypes(SevenHelperLevelResource::class, Post::class);
    $computed = $memo->computations();
    $own = resolve(MethodReturnTypeResolver::class)->resolve(BaseHelperLevelResource::class, 'staticConst');

    expect($published['static_named'])->toBe('string')
        ->and($own['type'] ?? null)->toBe('string')
        ->and($memo->computations())->toBe($computed)
        ->and(new SevenHelperLevelResource(new Post)->resolve(request())['static_named'])->toBe('base');
});
