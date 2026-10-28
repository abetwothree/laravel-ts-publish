<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;
use Workbench\App\Packages\Ui\HasIcon;
use Workbench\App\Packages\Ui\Node;

/**
 * A class whose own override stands alone with `inherit: false`, over a trait's, an interface's and a parent's.
 */
#[TsType(['type' => 'UiLone', 'import' => '@js/types/ui'], inherit: false)]
final class LoneChart extends Node implements HasIcon
{
    use AnnouncesHello;
}
