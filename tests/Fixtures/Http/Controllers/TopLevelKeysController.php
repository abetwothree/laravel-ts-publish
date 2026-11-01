<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/** Inertia actions whose top-level props hold dotted keys Inertia unpacks, and int keys renumbered per props array. */
class TopLevelKeysController
{
    /** Renders two dotted keys, one of them numeric. */
    public function dotted(): Response
    {
        return Inertia::render('TopLevelKeys/Dotted', ['auth.user' => 'x', '1.5' => 'y']);
    }

    /** Renders a dotted key that writes into the array an earlier key holds. */
    public function intoExisting(): Response
    {
        return Inertia::render('TopLevelKeys/IntoExisting', ['auth' => ['id' => 1], 'auth.name' => 'x']);
    }

    /** Renders a dotted key that writes into the array a later key holds. */
    public function existingAfter(): Response
    {
        return Inertia::render('TopLevelKeys/ExistingAfter', ['auth.name' => 'x', 'auth' => ['id' => 1]]);
    }

    /** Renders a dotted key whose head holds a string, which Inertia replaces. */
    public function overScalar(): Response
    {
        return Inertia::render('TopLevelKeys/OverScalar', ['a' => 'x', 'a.b' => 1]);
    }

    /** Renders one of two props arrays, each with an int key Laravel renumbers from 0. */
    public function ternary(Request $request): Response
    {
        return Inertia::render('TopLevelKeys/Ternary', $request->boolean('c') ? [7 => 'a'] : ['x' => 1, 8 => 'b']);
    }

    /** Renders the same component twice, each props array with an int key Laravel renumbers from 0. */
    public function twoRenders(Request $request): Response
    {
        if ($request->boolean('c')) {
            return Inertia::render('TopLevelKeys/TwoRenders', [7 => 'a']);
        }

        return Inertia::render('TopLevelKeys/TwoRenders', ['x' => 1, 8 => 'b']);
    }

    /** Casts a dotted key by its dotted name. */
    #[TsCasts(['auth.user' => '"fixed"'])]
    public function castDotted(): Response
    {
        return Inertia::render('TopLevelKeys/CastDotted', ['auth.user' => 'fixed', 'plain' => 1]);
    }

    /** Renders a local written a dotted key and an int key. */
    public function walked(): Response
    {
        $props = ['plain' => 1];
        $props['auth.user'] = 'x';
        $props[7] = 'y';

        return Inertia::render('TopLevelKeys/Walked', $props);
    }

    /** Renders a three-segment key and keys with an empty segment. */
    public function segments(): Response
    {
        return Inertia::render('TopLevelKeys/Segments', ['a.b.c' => 1, 'd..e' => 2, '.f' => 3, 'g.' => 4]);
    }

    /** Renders a dotted key only one props array holds, under a head both hold. */
    public function optionalDotted(Request $request): Response
    {
        return Inertia::render('TopLevelKeys/OptionalDotted', $request->boolean('c')
            ? ['auth' => ['id' => 1], 'auth.name' => 'x']
            : ['auth' => ['id' => 2]]);
    }

    /** Renders a local written one of two int keys, so Laravel sends either on 0. */
    public function walkedIntKeys(Request $request): Response
    {
        $props = ['x' => 1];

        if ($request->boolean('c')) {
            $props[7] = 'a';
        } else {
            $props[8] = 5;
        }

        return Inertia::render('TopLevelKeys/WalkedIntKeys', $props);
    }

    /** Renders an int key joined by `+` after one of two others. */
    public function plusIntKeys(Request $request): Response
    {
        return Inertia::render('TopLevelKeys/PlusIntKeys', ($request->boolean('c') ? [7 => 'a'] : [9 => 'z']) + [8 => 'b']);
    }

    /** Renders an optional prop on an int key before another, which keeps its number while Inertia leaves it out. */
    public function optionalIntKey(): Response
    {
        return Inertia::render('TopLevelKeys/OptionalIntKey', [7 => Inertia::optional(fn () => 'a'), 8 => 'b']);
    }

    /** Casts an int key one props array holds where the other sends another int key on the same number. */
    #[TsCasts(['8' => '"b"'])]
    public function castSharedNumber(Request $request): Response
    {
        return Inertia::render('TopLevelKeys/CastSharedNumber', $request->boolean('c') ? [7 => 'a'] : ['x' => 1, 8 => 'b']);
    }

    /** Renders a local written five int keys it may lack before one it always holds. */
    public function manyIntKeys(Request $request): Response
    {
        $props = [];

        if ($request->boolean('a')) {
            $props[1] = 'a';
        }

        if ($request->boolean('b')) {
            $props[2] = 'b';
        }

        if ($request->boolean('c')) {
            $props[3] = 'c';
        }

        if ($request->boolean('d')) {
            $props[4] = 'd';
        }

        if ($request->boolean('e')) {
            $props[5] = 'e';
        }

        $props[6] = 'f';

        return Inertia::render('TopLevelKeys/ManyIntKeys', $props);
    }

    /** Renders two same-named enums under one dotted head, beside one of them at the top level. */
    public function sameNamedNested(): Response
    {
        return Inertia::render('TopLevelKeys/SameNamedNested', [
            'pair.a' => Status::Draft,
            'pair.b' => CrmStatus::Lead,
            'a' => Status::Draft,
        ]);
    }
}
