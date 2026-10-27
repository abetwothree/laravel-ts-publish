<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Inertia\Inertia;
use Inertia\Response;

/** A test-only controller whose actions hand their props to an injected collaborator, one of them final. */
class ControllerWithTierProps
{
    public function __construct(
        private readonly TierPropsBuilder $tiers,
        private readonly FinalTierPropsBuilder $finalTiers,
    ) {}

    /** Render the props the injected collaborator builds. */
    public function index(): Response
    {
        return Inertia::render('Tier/Delegated', $this->tiers->build());
    }

    /** Render the props the final collaborator builds. */
    public function exact(): Response
    {
        return Inertia::render('Tier/Final', $this->finalTiers->build());
    }
}
