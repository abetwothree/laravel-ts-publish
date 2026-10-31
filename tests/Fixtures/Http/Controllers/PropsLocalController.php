<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Workbench\App\Models\Post;

/** Inertia actions whose props argument is a local the action writes before, and after, its render call. */
class PropsLocalController
{
    /** Renders a literal local grown by a key write. */
    public function grown(): Response
    {
        $props = ['heading' => 'x'];
        $props['total'] = 5;

        return Inertia::render('PropsLocal/Grown', $props);
    }

    /** Renders a local assigned only in a block whose other branch throws. */
    public function inBlock(Request $request): Response
    {
        if ($request->boolean('x')) {
            $props = ['heading' => 'x', 'n' => 1];
        } else {
            throw new NotFoundHttpException;
        }

        return Inertia::render('PropsLocal/InBlock', $props);
    }

    /** Renders a local each arm of an `if` assigns its own keys. */
    public function joined(Request $request): Response
    {
        if ($request->boolean('full')) {
            $props = ['heading' => 'full', 'n' => 1];
        } else {
            $props = ['heading' => 'short'];
        }

        return Inertia::render('PropsLocal/Joined', $props);
    }

    /** Renders a block-assigned local joined with a literal by `+`. */
    public function plus(Request $request): Response
    {
        if ($request->boolean('x')) {
            $base = ['a' => 1];
        } else {
            $base = ['a' => 2];
        }

        return Inertia::render('PropsLocal/Plus', $base + ['k' => 1]);
    }

    /** Renders a prop joining a block-assigned local with a literal by `+`. */
    public function plusProp(Request $request): Response
    {
        if ($request->boolean('x')) {
            $base = ['a' => 1];
        } else {
            $base = ['a' => 2];
        }

        return Inertia::render('PropsLocal/PlusProp', ['merged' => $base + ['k' => 1]]);
    }

    /**
     * Renders a prop joining, by `+`, a parameter's write that reads the parameter with a literal.
     *
     * @param  array<string, mixed>  $opts
     */
    public function plusParamSelf(Request $request, array $opts = []): Response
    {
        $opts = ['a' => $opts['a'] ?? 1];

        return Inertia::render('PropsLocal/PlusParamSelf', ['m' => $opts + ['k' => 1]]);
    }

    /** Renders a local the action writes again after the render call. */
    public function late(): Response
    {
        $props = ['heading' => 'x'];
        $page = Inertia::render('PropsLocal/Late', $props);
        $props['late'] = 1;

        return $page;
    }

    /** Renders a local a loop writes keys into. */
    public function looped(): Response
    {
        $props = ['heading' => 'x'];

        foreach ([1, 2] as $id) {
            $props["k{$id}"] = $id;
        }

        return Inertia::render('PropsLocal/Looped', $props);
    }

    /** Renders a `+` whose left side holds its key only when the request asks; otherwise the right side's lands. */
    public function plusOptional(Request $request): Response
    {
        $base = [];

        if ($request->boolean('x')) {
            $base['a'] = 1;
        }

        return Inertia::render('PropsLocal/PlusOptional', $base + ['a' => 'x']);
    }

    /** Renders a local whose key a block may set, which `??=` fills otherwise. */
    public function coalesceAssigned(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['a'] = 1;
        }

        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceAssigned', $props);
    }

    /** Renders a local whose key holds an optional prop, which `??=` keeps, since it is not null. */
    public function coalesceOptionalProp(): Response
    {
        $props = ['heading' => 'x', 'a' => Inertia::optional(fn (): int => 1)];
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceOptionalProp', $props);
    }

    /** Renders a local whose key holds a deferred prop, which `??=` keeps, since it is not null. */
    public function coalesceDeferProp(): Response
    {
        $props = ['heading' => 'x', 'a' => Inertia::defer(fn (): int => 1)];
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceDeferProp', $props);
    }

    /** Renders a local whose key holds a closure resolving to null, which `??=` keeps, as the closure is not null. */
    public function coalesceClosureNull(): Response
    {
        $props = ['heading' => 'x', 'a' => fn () => null];
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceClosureNull', $props);
    }

    /** Renders a local whose key holds a closure that may resolve to null, which `??=` keeps. */
    public function coalesceClosureNullable(Request $request): Response
    {
        $props = ['heading' => 'x', 'a' => fn (): ?int => $request->boolean('x') ? 1 : null];
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceClosureNullable', $props);
    }

    /** Renders a local whose key holds an optional prop that may resolve to null, which `??=` keeps. */
    public function coalesceOptionalNullable(Request $request): Response
    {
        $props = ['heading' => 'x', 'a' => Inertia::optional(fn (): ?int => $request->boolean('x') ? 1 : null)];
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceOptionalNullable', $props);
    }

    /** Renders a local whose key holds a deferred prop that may resolve to null, which `??=` keeps. */
    public function coalesceDeferNullable(Request $request): Response
    {
        $props = ['heading' => 'x', 'a' => Inertia::defer(fn (): ?int => $request->boolean('x') ? 1 : null)];
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceDeferNullable', $props);
    }

    /** Renders a local whose key holds an optional prop resolving to null, which `??=` keeps. */
    public function coalesceOptionalOnlyNull(): Response
    {
        $props = ['heading' => 'x', 'a' => Inertia::optional(fn () => null)];
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceOptionalOnlyNull', $props);
    }

    /** Renders a local whose key a block may set to a closure resolving to null, which `??=` fills otherwise. */
    public function coalesceClosureInBlock(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['a'] = fn (): ?int => null;
        }

        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceClosureInBlock', $props);
    }

    /** Renders a local whose key holds a closure on one branch and null on the other, which `??=` fills. */
    public function coalesceClosureOrNull(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['a'] = fn (): int => 1;
        } else {
            $props['a'] = null;
        }

        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceClosureOrNull', $props);
    }

    /** Renders a local whose key a block may set to a closure, which `??=` fills with null, a string, then a number. */
    public function coalesceClosureRepeated(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['a'] = fn (): ?int => null;
        }

        $props['a'] ??= null;
        $props['a'] ??= 'none';
        $props['a'] ??= 0;

        return Inertia::render('PropsLocal/CoalesceClosureRepeated', $props);
    }

    /** Renders a local whose key holds a local's closure that may resolve to null, which `??=` keeps. */
    public function coalesceClosureLocal(Request $request): Response
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;
        $props = ['heading' => 'x'];
        $props['a'] = $f;
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceClosureLocal', $props);
    }

    /** Renders a local whose key holds a first-class callable resolving to null, which `??=` keeps. */
    public function coalesceFirstClassNullable(): Response
    {
        $props = ['heading' => 'x'];
        $props['a'] = $this->maybe(...);
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceFirstClassNullable', $props);
    }

    /** Renders a local whose key holds an always prop over a value that may be null, which `??=` keeps. */
    public function coalesceAlwaysNullable(): Response
    {
        $props = ['heading' => 'x', 'a' => Inertia::always($this->maybe())];
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceAlwaysNullable', $props);
    }

    /** Renders a local whose key holds a closure on either arm of a ternary, which `??=` keeps. */
    public function coalesceTernaryClosures(Request $request): Response
    {
        $props = ['heading' => 'x'];
        $props['a'] = $request->boolean('x') ? fn (): ?int => null : fn (): int => 1;
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceTernaryClosures', $props);
    }

    /** Renders a local whose key holds a closure on one arm of a ternary and null on the other, which `??=` fills. */
    public function coalesceTernaryClosureOrNull(Request $request): Response
    {
        $props = ['heading' => 'x'];
        $props['a'] = $request->boolean('x') ? fn (): ?int => null : null;
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceTernaryClosureOrNull', $props);
    }

    /** Renders a local whose key holds a local holding a closure on one arm of a ternary and null on the other. */
    public function coalesceLocalTernary(Request $request): Response
    {
        $f = $request->boolean('x') ? fn (): ?int => null : null;
        $props = ['heading' => 'x'];
        $props['a'] = $f;
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceLocalTernary', $props);
    }

    /** Renders a local whose key holds a closure that never resolves to null on one arm and null on the other. */
    public function coalesceTernaryIntClosureOrNull(Request $request): Response
    {
        $props = ['heading' => 'x'];
        $props['a'] = $request->boolean('x') ? fn (): int => 1 : null;
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceTernaryIntClosureOrNull', $props);
    }

    /** Renders a local whose key holds a request field or, where it is missing, a closure, which `??=` may fill. */
    public function coalesceFieldOrClosure(Request $request): Response
    {
        $props = ['heading' => 'x'];
        $props['a'] = $request->input('q') ?? fn (): ?int => null;
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceFieldOrClosure', $props);
    }

    /** Renders a local whose key holds a closure on every arm of a `match`, which `??=` keeps. */
    public function coalesceMatchClosures(Request $request): Response
    {
        $props = ['heading' => 'x'];
        $props['a'] = match ($request->input('k')) {
            'a' => fn (): ?int => null,
            default => fn (): int => 1,
        };
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceMatchClosures', $props);
    }

    /** Renders a local whose key holds true or, past `?:`, a closure resolving to null, which `??=` may fill. */
    public function coalesceTrueOrClosure(Request $request): Response
    {
        $props = ['heading' => 'x'];
        $props['a'] = $request->boolean('x') ?: fn (): ?int => null;
        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/CoalesceTrueOrClosure', $props);
    }

    /** Renders a local whose key a block may set, which a write reading the local itself fills otherwise. */
    public function coalesceSelfRead(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['a'] = 1;
        }

        $props['a'] = $props['a'] ?? 'none';

        return Inertia::render('PropsLocal/CoalesceSelfRead', $props);
    }

    /** Renders a local whose key a loop may set to a value no reading types, which `??=` fills otherwise. */
    public function loopedCoalesce(Request $request): Response
    {
        $props = ['heading' => 'x'];

        foreach ($request->boolean('x') ? [1] : [] as $id) {
            $props['a'] = $id;
        }

        $props['a'] ??= 'none';

        return Inertia::render('PropsLocal/LoopedCoalesce', $props);
    }

    /** Renders a local whose key a write fills from a key a loop may set to a value no reading types. */
    public function loopedSelfRead(Request $request): Response
    {
        $props = ['heading' => 'x'];

        foreach ($request->boolean('x') ? [1] : [] as $id) {
            $props['a'] = $id;
        }

        $props['b'] = $props['a'] ?? 'none';

        return Inertia::render('PropsLocal/LoopedSelfRead', $props);
    }

    /** Renders a local replaced by a literal that reads a key a block may have set on the local before. */
    public function selfReplaced(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['a'] = 1;
        }

        $props = ['a' => $props['a'] ?? 'none'];

        return Inertia::render('PropsLocal/SelfReplaced', $props);
    }

    /** Renders a `+` whose left side holds keys a loop names, one of which the right side names too. */
    public function loopedPlus(): Response
    {
        $props = ['heading' => 'x'];

        foreach ([1, 2] as $id) {
            $props["k{$id}"] = $id;
        }

        return Inertia::render('PropsLocal/LoopedPlus', $props + ['k1' => 'x']);
    }

    /** Renders a local a `+=` adds a key to that a block may have set first. */
    public function plusAssigned(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['a'] = 1;
        }

        $props += ['a' => 'none'];

        return Inertia::render('PropsLocal/PlusAssigned', $props);
    }

    /** Renders a local a loop writes twice, the first write kept by a `continue`. */
    public function continued(Request $request): Response
    {
        $props = [];

        foreach ([1] as $ignored) {
            if ($request->boolean('x')) {
                $props['a'] = 1;

                continue;
            }

            $props['a'] = 'x';
        }

        return Inertia::render('PropsLocal/Continued', $props);
    }

    /** Renders a local a `try` writes twice, the second write skipped where the request does not ask. */
    public function tried(Request $request): Response
    {
        $props = [];

        try {
            $props['a'] = 'x';
            $request->boolean('x') ?: throw new RuntimeException('skipped');
            $props['a'] = 1;
        } catch (RuntimeException) {
        }

        return Inertia::render('PropsLocal/Tried', $props);
    }

    /** Renders a local each case of a `switch` writes. */
    public function switched(Request $request): Response
    {
        $props = ['heading' => 'x'];

        switch ($request->boolean('x')) {
            case true:
                $props['n'] = 1;

                break;
            default:
                $props['n'] = 'two';
        }

        return Inertia::render('PropsLocal/Switched', $props);
    }

    /** Renders a callback's own `$props`, which the action's local of that name does not reach. */
    public function shadowed(): Response
    {
        $props = ['heading' => 'x'];
        $props['n'] = 1;

        return collect([['own' => 1]])->map(fn (array $props): Response => Inertia::render('PropsLocal/Shadowed', $props))
            ->firstOrFail();
    }

    /** Renders a `+` whose left side spreads keys the request decides, which may hold the right side's key. */
    public function opaquePlus(Request $request): Response
    {
        return Inertia::render('PropsLocal/OpaquePlus', [...$request->all(), 'a' => 1] + ['k' => 1]);
    }

    /** Renders a local holding a callable and a resource, which Inertia calls and resolves. */
    public function stamped(Post $post): Response
    {
        $props = ['stamp' => now(...)];
        $props['card'] = new UserCardResource($post->author);
        $props['extra'] = UserCardResource::make($post->author)->additional(['meta' => 1]);

        return Inertia::render('PropsLocal/Stamped', $props);
    }

    /** Renders the literal the stamped() local builds, for its type to match. */
    public function stampedLiteral(Post $post): Response
    {
        return Inertia::render('PropsLocal/StampedLiteral', [
            'stamp' => now(...),
            'card' => new UserCardResource($post->author),
            'extra' => UserCardResource::make($post->author)->additional(['meta' => 1]),
        ]);
    }

    /** A label that may be missing, which one action passes as a callable and another wraps in an always prop. */
    private function maybe(): ?string
    {
        return null;
    }
}
