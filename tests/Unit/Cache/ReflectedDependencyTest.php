<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Workbench\App\Casts\CoordinateCast;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\NarrowedImageableResource;
use Workbench\App\Http\Resources\PostStatsResource;
use Workbench\App\Http\Resources\ReceiverMethodResource;
use Workbench\App\Http\Resources\ShiftResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Warehouse;
use Workbench\App\Services\ShiftClock;
use Workbench\App\ValueObjects\Coordinate;
use Workbench\App\ValueObjects\PostStats;
use Workbench\App\ValueObjects\ShiftNote;

/**
 * The files recorded while a callback runs, with recording stopped afterwards.
 *
 * @return list<string>
 */
function recordedWhile(Closure $read): array
{
    DependencyRecorder::start();

    try {
        $read();

        return DependencyRecorder::paths();
    } finally {
        DependencyRecorder::stop();
    }
}

/**
 * The file that declares a class.
 */
function fileOf(string $class): string
{
    return (string) new ReflectionClass($class)->getFileName();
}

it('records the enum a resource calls a method on through a cast attribute', function () {
    $paths = recordedWhile(fn () => new ResourceTransformer(ReceiverMethodResource::class));

    expect($paths)->toContain(fileOf(Priority::class));
});

it('records the service a resource calls a method on through a subject method', function () {
    $paths = recordedWhile(fn () => new ResourceTransformer(ShiftResource::class));

    expect($paths)->toContain(fileOf(ShiftClock::class));
});

it('records the value object a resource reads a public property off', function () {
    $paths = recordedWhile(fn () => new ResourceTransformer(PostStatsResource::class));

    expect($paths)->toContain(fileOf(PostStats::class));
});

it('records the receiver class and the trait that declares the method it inherits', function () {
    $paths = recordedWhile(fn () => new ResourceTransformer(ReceiverMethodResource::class));

    expect($paths)->toContain(fileOf(Carbon::class))
        ->and($paths)->toContain((string) new ReflectionMethod(Carbon::class, 'toDateString')->getFileName());
});

it('records the models an instanceof narrowing reads members of', function () {
    $paths = recordedWhile(fn () => new ResourceTransformer(NarrowedImageableResource::class));

    expect($paths)->toContain(fileOf(Post::class));
});

it('records the cast class and value object a model publishes the shape of', function () {
    $paths = recordedWhile(fn () => new ModelTransformer(Warehouse::class));

    expect($paths)->toContain(fileOf(CoordinateCast::class))
        ->and($paths)->toContain(fileOf(Coordinate::class));
});

it('records the enums a model names, since #[TsEnum] renames their type', function () {
    $paths = recordedWhile(fn () => new ModelTransformer(Warehouse::class));

    expect($paths)->toContain(fileOf(Status::class))
        ->and($paths)->toContain(fileOf(Priority::class));
});

it('records the class a chain link reads a return class from', function () {
    $paths = recordedWhile(
        fn () => expect(resolve(ReceiverClassResolver::class)->returnClasses(ShiftClock::class, 'handoverNote'))->toBe([ShiftNote::class]),
    );

    expect($paths)->toContain(fileOf(ShiftClock::class));
});

it('records every class of a union receiver even when the call declines', function () {
    $scope = new AnalysisScope(new ReflectionClass(ShiftResource::class));
    $receiver = new ReceiverType([ShiftClock::class, PostStats::class]);

    $paths = recordedWhile(
        fn () => expect(resolve(ReceiverMethodReturnResolver::class)->resolve($receiver, 'lastFault', $scope))->toBeNull(),
    );

    expect($paths)->toContain(fileOf(ShiftClock::class))
        ->and($paths)->toContain(fileOf(PostStats::class));
});

it('replays the receiver class when a method-return answer is reused', function () {
    resolve(AnalysisMemo::class)->reset();
    $resolver = resolve(MethodReturnTypeResolver::class);

    $first = recordedWhile(fn () => $resolver->resolve(Priority::class, 'label'));
    $reused = recordedWhile(fn () => expect($resolver->resolve(Priority::class, 'label')['type'] ?? null)->toBe('string'));

    expect($first)->toContain(fileOf(Priority::class))
        ->and($reused)->toContain(fileOf(Priority::class));
});

it('replays the cast class when a cached attribute class is reused', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $first = recordedWhile(fn () => expect($resolver->resolveAttributeClass(Warehouse::class, 'coordinate_data'))->toBe(Coordinate::class));
    $reused = recordedWhile(fn () => $resolver->resolveAttributeClass(Warehouse::class, 'coordinate_data'));

    expect($first)->toContain(fileOf(CoordinateCast::class))
        ->and($reused)->toContain(fileOf(CoordinateCast::class))
        ->and($reused)->toContain(fileOf(Warehouse::class));
});

it('records a helper function file on every read, not only the first', function () {
    $helpers = (string) new ReflectionFunction('route')->getFileName();

    LaravelTsPublish::nativePhpFunctionReturnedTypes('route');
    $reused = recordedWhile(fn () => LaravelTsPublish::nativePhpFunctionReturnedTypes('route'));

    expect($reused)->toContain($helpers);
});

it('records an interface whose jsonSerialize() decides the type', function () {
    $paths = recordedWhile(fn () => expect(LaravelTsPublish::toTsType(CarbonInterface::class)['type'])->toBe('string'));

    expect($paths)->toContain(fileOf(CarbonInterface::class));
});

it('records a class again on every call, so a memo frame marked after the first still holds its files', function () {
    DependencyRecorder::start();

    try {
        DependencyRecorder::recordClass(ShiftClock::class);
        $first = DependencyRecorder::since(0);
        $mark = DependencyRecorder::mark();
        DependencyRecorder::recordClass(ShiftClock::class);

        expect($first)->toContain(fileOf(ShiftClock::class))
            ->and(DependencyRecorder::since($mark))->toBe($first);
    } finally {
        DependencyRecorder::stop();
    }
});
