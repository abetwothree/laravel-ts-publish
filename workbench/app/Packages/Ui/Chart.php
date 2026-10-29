<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;

/**
 * Inherits a trait's, an interface's and a parent's override, so it publishes the composed `ChartType`.
 */
final class Chart extends Node implements HasIcon
{
    use AnnouncesHello;
}
