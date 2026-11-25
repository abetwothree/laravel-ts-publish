<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only controller rendering the app's or the CRM's `User` under one prop, beside a render no rule types (GH-76).
 */
final class SameNamedUntypedPropsController
{
    /**
     * Renders each user, or an untyped value.
     */
    public function returns(Request $request): Response
    {
        if ($request->boolean('a')) {
            return Inertia::render('SameNamed/Returns', ['user' => User::first()]);
        }

        if ($request->boolean('b')) {
            return Inertia::render('SameNamed/Returns', ['user' => json_decode((string) $request->input('x'))]);
        }

        return Inertia::render('SameNamed/Returns', ['user' => CrmUser::first()]);
    }

    /**
     * Renders one of two literals, each holding one user.
     */
    public function literals(Request $request): Response
    {
        return Inertia::render('SameNamed/Literals', $request->boolean('a') ? ['user' => User::first()] : ['user' => CrmUser::first()]);
    }
}
