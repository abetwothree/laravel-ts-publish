<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\Spreads\WhenKeySpreadResource;
use Workbench\App\ValueObjects\CreditLine;
use Workbench\App\ValueObjects\MailRecipient;

/**
 * Props a block builds and renders, a default over a key a branch may skip, a spread resolve() (GH-161 B1, B4, B5, A9).
 */
class InertiaPropsWalkController
{
    /**
     * A props local built and rendered inside one `if`.
     */
    public function blockBuilt(Request $request): Response
    {
        if ($request->boolean('x')) {
            $props = ['heading' => 'x'];
            $props['total'] = 5;

            return Inertia::render('PropsWalk/BlockBuilt', $props);
        }

        return Inertia::render('PropsWalk/BlockBuilt', ['heading' => 'y']);
    }

    /**
     * A top-level props local given a key in a block that renders it and exits.
     */
    public function exitingKeyWrite(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['total'] = 5;

            return Inertia::render('PropsWalk/ExitingKeyWrite', $props);
        }

        return Inertia::render('PropsWalk/ExitingKeyWrite', $props);
    }

    /**
     * A whole write nested in a block that renders the local and exits.
     */
    public function exitingNestedWhole(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            if ($request->boolean('y')) {
                $props = ['total' => 5];
            }

            return Inertia::render('PropsWalk/ExitingNestedWhole', $props);
        }

        return Inertia::render('PropsWalk/ExitingNestedWhole', $props);
    }

    /**
     * A key a branch may skip, given a default by reading itself with `??`.
     */
    public function coalescedSelfRead(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['total'] = 5;
        }

        $props['total'] = $props['total'] ?? 'none';

        return Inertia::render('PropsWalk/CoalescedSelfRead', $props);
    }

    /**
     * A `+` of a default over a key a branch may skip.
     */
    public function plusOperator(Request $request): Response
    {
        $props = ['heading' => 'x'];

        if ($request->boolean('x')) {
            $props['total'] = 5;
        }

        return Inertia::render('PropsWalk/PlusOperator', $props + ['total' => 'none']);
    }

    /**
     * A resource's resolve() holding a `when()` key, alone and spread over an earlier key.
     */
    public function resolvedWhenSpread(Request $request): Response
    {
        $line = new CreditLine(new MailRecipient('Ada', 'a@x'), 'hi', $request->boolean('x'));

        return Inertia::render('PropsWalk/ResolvedWhenSpread', [
            'credit' => WhenKeySpreadResource::make($line)->resolve(),
            'credit_over_key' => ['by' => 0, ...WhenKeySpreadResource::make($line)->resolve()],
        ]);
    }
}
