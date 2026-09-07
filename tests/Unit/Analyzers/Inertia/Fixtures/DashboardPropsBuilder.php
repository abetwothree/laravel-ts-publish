<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

class DashboardPropsBuilder
{
    /**
     * @return array{heading: string, total: int}
     */
    public function build(): array
    {
        return ['heading' => 'Dashboard', 'total' => 3];
    }
}
