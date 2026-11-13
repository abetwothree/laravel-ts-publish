<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\AlwaysProp;
use Inertia\Inertia;
use Inertia\OptionalProp;
use Inertia\Response;

/** Inertia actions whose prop is a closure returning `??` over a held closure or prop, which Inertia calls once. */
class CallableReturnsController
{
    /** Renders an arrow function returning `??` over a local closure. */
    public function arrow(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return Inertia::render('CallableReturns/Arrow', ['a' => fn () => $f ?? 5]);
    }

    /** Renders a closure that uses a local closure and returns `??` over it. */
    public function closure(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return Inertia::render('CallableReturns/Closure', ['a' => function () use ($f) {
            return $f ?? 5;
        }]);
    }

    /** Renders an always prop over that arrow function. */
    public function always(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return Inertia::render('CallableReturns/Always', ['a' => Inertia::always(fn () => $f ?? 5)]);
    }

    /** Renders an optional prop over that arrow function. */
    public function optional(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return Inertia::render('CallableReturns/Optional', ['h' => 'x', 'a' => Inertia::optional(fn () => $f ?? 5)]);
    }

    /** Renders that arrow function held in a local. */
    public function local(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;
        $g = fn () => $f ?? 5;

        return Inertia::render('CallableReturns/Local', ['a' => $g]);
    }

    /** Renders an arrow function returning `??` over a local that holds a closure or null. */
    public function joined(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;
        $h = $request->boolean('y') ? $f : null;

        return Inertia::render('CallableReturns/Joined', ['a' => fn () => $h ?? 5]);
    }

    /** Renders an arrow function returning `??` over a local first-class callable. */
    public function firstClass(Request $request): Response
    {
        $c = $this->maybe(...);

        return Inertia::render('CallableReturns/FirstClass', ['a' => fn () => $c ?? 'z']);
    }

    /** Renders a props local whose key holds an arrow function returning `??` over a local closure. */
    public function propsLocal(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;
        $props = ['h' => 'x'];
        $props['a'] = fn () => $f ?? 5;

        return Inertia::render('CallableReturns/PropsLocal', $props);
    }

    /** Renders an arrow function returning `??` over a local always prop, which Inertia resolves past the call. */
    public function heldProp(Request $request): Response
    {
        $p = Inertia::always(fn (): ?int => $request->boolean('x') ? 1 : null);

        return Inertia::render('CallableReturns/HeldProp', ['a' => fn () => $p ?? 5]);
    }

    /** Renders an arrow function returning an array whose key holds `??` over a local closure. */
    public function inArray(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return Inertia::render('CallableReturns/InArray', ['a' => fn () => ['k' => $f ?? 5]]);
    }

    /** Renders a `new` always prop over an arrow function returning `??` over a local closure. */
    public function newAlways(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return Inertia::render('CallableReturns/NewAlways', ['a' => new AlwaysProp(fn () => $f ?? 5)]);
    }

    /** Renders a `new` optional prop over that arrow function. */
    public function newOptional(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return Inertia::render('CallableReturns/NewOptional', ['h' => 'x', 'a' => new OptionalProp(fn () => $f ?? 5)]);
    }

    /** Renders a `new` always prop over an arrow function returning a local closure. */
    public function newAlwaysClosure(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return Inertia::render('CallableReturns/NewAlwaysClosure', ['a' => new AlwaysProp(fn () => $f)]);
    }

    /** A label that may be missing. */
    private function maybe(): ?string
    {
        return null;
    }
}
