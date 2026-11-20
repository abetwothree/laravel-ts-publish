<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisResult;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard\ArmKind;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard\GuardCase;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard\GuardCases;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard\GuardClassFiles;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard\PublishedClasses;

/*
 * The class-queue guard (GH-105's V1): seeded bodies whose union arms hold classes that print alike, each published
 * token read through the result's imports to the class it names. Re-run a failure with the GUARD_SEED it prints;
 * GUARD_CASES sets how many bodies each family draws.
 */

/**
 * The families today's code still fails, by the task that fixes them.
 *
 * @return array<string, list<array{ArmKind, ArmKind}>>
 */
function classQueueGuardPending(): array
{
    return [
        // A wrap prints its enum's value type until the late rewrite, so it folds or swaps with a class printed alike.
        'Task 5' => [
            [ArmKind::WrapAppStatus, ArmKind::WrapCrmStatus],
            [ArmKind::WrapAppStatus, ArmKind::ReadAppStatus],
            [ArmKind::WrapAppStatus, ArmKind::ReadCrmStatus],
            [ArmKind::WrapAppStatus, ArmKind::Rows],
            [ArmKind::WrapCrmStatus, ArmKind::ReadAppStatus],
            [ArmKind::WrapCrmStatus, ArmKind::ReadCrmStatus],
            [ArmKind::WrapCrmStatus, ArmKind::Rows],
        ],
        // A model and a resource printed alike are never told apart by class, so they fold or their aliases swap.
        'Task 9' => [
            [ArmKind::AddressModel, ArmKind::AddressResource],
        ],
    ];
}

/**
 * Every pair of two arm kinds, each a dataset row named for its kinds: the ones $pending fixes, or with null, the
 * ones no task is pending on.
 *
 * @return array<string, array{ArmKind, ArmKind}>
 */
function classQueueGuardFamilies(?string $pending): array
{
    $kinds = ArmKind::cases();
    $families = [];

    foreach ($kinds as $index => $first) {
        foreach (array_slice($kinds, $index + 1) as $second) {
            $task = null;

            foreach (classQueueGuardPending() as $name => $pairs) {
                $task = in_array([$first, $second], $pairs, true) ? $name : $task;
            }

            if ($task === $pending) {
                $families["{$first->value} × {$second->value}"] = [$first, $second];
            }
        }
    }

    return $families;
}

/**
 * An integer environment override, or $default where it is unset.
 */
function classQueueGuardSetting(string $name, int $default): int
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        return $default;
    }

    throw_unless(
        preg_match('/^\d+$/', $value) === 1,
        InvalidArgumentException::class,
        "{$name} must be a whole number, not {$value}.",
    );

    return (int) $value;
}

/**
 * Publish $case and compare each union member's classes, in order, with its arm's; the mismatch, or null.
 */
function classQueueGuardMismatch(GuardCase $case, GuardClassFiles $files): ?string
{
    $result = resolve(AstEngine::class)->analyze($files->load($case), 'toArray', null, PublishedClasses::FROM);
    $type = $result->properties[0]['type'];
    $read = PublishedClasses::of($result);
    $published = array_map($read->in(...), $case->shape->members($type));
    $expected = $case->expected();

    if ($published === $expected) {
        return null;
    }

    return implode("\n", [
        "  {$case->label()}",
        "  body:      'v' => {$case->value()}",
        "  published: {$type}",
        '  imports:   '.classQueueGuardImports($result),
        '  read:      '.json_encode($published, JSON_UNESCAPED_SLASHES),
        '  expected:  '.json_encode($expected, JSON_UNESCAPED_SLASHES),
    ]);
}

/**
 * A result's type and value imports on one line.
 */
function classQueueGuardImports(AnalysisResult $result): string
{
    return 'type '.json_encode($result->typeImports, JSON_UNESCAPED_SLASHES)
        .', value '.json_encode($result->valueImports, JSON_UNESCAPED_SLASHES);
}

/**
 * The smallest arm set of $case that still fails, found by dropping one arm at a time while the body still fails.
 *
 * @return array{GuardCase, string}
 */
function classQueueGuardShrink(GuardCase $case, string $mismatch, GuardClassFiles $files): array
{
    do {
        $shrunk = false;

        foreach (array_keys($case->arms) as $index) {
            $smaller = $case->without($index);
            $smallerMismatch = $smaller === null ? null : classQueueGuardMismatch($smaller, $files);

            if ($smaller !== null && $smallerMismatch !== null) {
                [$case, $mismatch, $shrunk] = [$smaller, $smallerMismatch, true];

                break;
            }
        }
    } while ($shrunk);

    return [$case, $mismatch];
}

$guard = function (ArmKind $first, ArmKind $second) {
    $seed = classQueueGuardSetting('GUARD_SEED', 160);
    $count = classQueueGuardSetting('GUARD_CASES', 120);
    $checked = [];

    foreach (GuardCases::of($first, $second, $seed, $count) as $index => $case) {
        // A family's bodies repeat once GUARD_CASES nears the count of its distinct bodies; each is read once.
        if (isset($checked[$case->label()])) {
            continue;
        }

        $checked[$case->label()] = true;
        $mismatch = classQueueGuardMismatch($case, $this->guardFiles);

        if ($mismatch !== null) {
            [, $smallest] = classQueueGuardShrink($case, $mismatch, $this->guardFiles);
            $body = $index + 1;

            $this->fail(implode("\n", [
                "Body {$body} of {$count} (GUARD_SEED={$seed}) names other classes than its arms hold:",
                $mismatch,
                'Smallest failing arm set:',
                $smallest,
            ]));
        }
    }

    expect($checked)->not->toBeEmpty();
};

beforeEach(function () {
    config()->set('ts-publish.enums.use_tolki_package', true);
    $this->guardFiles = new GuardClassFiles;
});

afterEach(function () {
    $this->guardFiles->remove();
});

test('each token a union publishes names the class its arm holds', $guard)
    ->with(classQueueGuardFamilies(null));

test('each token of a union holding a wrap names its arm\'s class', $guard)
    ->with(classQueueGuardFamilies('Task 5'))
    ->todo('Task 5');

test('each token of a union of a model and a resource of one name names its arm\'s class', $guard)
    ->with(classQueueGuardFamilies('Task 9'))
    ->todo('Task 9');
