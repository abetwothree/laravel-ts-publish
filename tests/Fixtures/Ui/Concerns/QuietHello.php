<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;

/**
 * A trait whose override stands alone with `inherit: false`, over the trait it uses.
 */
#[TsType(['type' => 'UiQuiet', 'import' => '@js/types/ui'], inherit: false)]
trait QuietHello
{
    use AnnouncesHello;
}
