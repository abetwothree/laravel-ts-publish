<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

/**
 * A plain value object whose members are overridden classes, so its shape keeps each member's override and import.
 */
final class Board
{
    public function __construct(
        public Panel $panel = new Panel,
        public Chart $chart = new Chart,
        public ?Gauge $gauge = null,
        public Pennies $total = new Pennies,
        /** @var list<Chart> */
        public array $charts = [],
    ) {}
}
