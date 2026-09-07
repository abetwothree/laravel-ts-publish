<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Inertia\Inertia;
use Inertia\Response;

class ControllerWithDelegatedProps
{
    public function __construct(private readonly DashboardPropsBuilder $props) {}

    public function index(): Response
    {
        return Inertia::render('Dashboard/Delegated', $this->props->build());
    }

    public function helper(): Response
    {
        return inertia('Dashboard/Helper', ['label' => 'hi']);
    }

    public function helperChain(): Response
    {
        return inertia()->render('Dashboard/HelperChain', ['label' => 'hi']);
    }
}
